<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\TherapySession;
use App\Models\BranchRoom;
use App\Models\Staff;
use App\Models\Patient;
use App\Models\RoomAvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class BookingService
{
    /**
     * Create a booking atomically with grid maintenance
     * 
     * @param array<string, mixed> $data
     * @return TherapySession
     * @throws \RuntimeException
     */
    public function createBooking(array $data): TherapySession
    {
        return DB::transaction(function () use ($data) {
            // Extract required fields
            $roomId = $data['room_id'] ?? null;
            $staffId = $data['staff_id'] ?? null;
            $branchId = $data['branch_id'];
            $treatmentId = $data['treatment_id'];
            $patientId = $data['patient_id'] ?? null;
            $date = $data['date'];
            $startTime = $data['start_time'];
            $endTime = $data['end_time'] ?? null;

            // Calculate end time if not provided (1 hour session)
            if (!$endTime) {
                $startCarbon = Carbon::parse($date . ' ' . $startTime);
                $endTime = $startCarbon->copy()->addHour()->format('H:i:s');
            }

            // Lock resources to prevent concurrent bookings
            if ($roomId) {
                $room = BranchRoom::lockForUpdate()->find($roomId);
                if (!$room) {
                    throw new \RuntimeException('Room not found');
                }

                // CRITICAL: Verify gender compatibility before booking
                $patientGender = $this->getPatientGender($patientId, $data);
                if ($room->gender !== 'Unisex' && $room->gender !== $patientGender) {
                    throw new \RuntimeException(
                        'Gender mismatch: Patient gender does not match room gender constraint'
                    );
                }
            }

            if ($staffId) {
                $staff = Staff::lockForUpdate()->find($staffId);
                if (!$staff) {
                    throw new \RuntimeException('Staff not found');
                }
            }

            // Re-verify availability (double-check) - Check for overlapping bookings
            if ($roomId) {
                $startCarbon = Carbon::parse($date . ' ' . $startTime);
                $bookingEndTime = $endTime ? Carbon::parse($date . ' ' . $endTime) : $startCarbon->copy()->addHour();
                
                // Check for any overlapping bookings (not just exact time matches)
                // Overlap occurs when: existing_start < new_end AND existing_end > new_start
                $overlappingBookings = TherapySession::where('room_id', $roomId)
                    ->where('date', $date)
                    ->where('status', '!=', 'Cancelled')
                    ->where(function ($query) use ($startCarbon, $bookingEndTime) {
                        $query->where(function ($q) use ($startCarbon, $bookingEndTime) {
                            // MariaDB/MySQL syntax: ADDTIME for time + interval
                            $q->where('start_time', '<', $bookingEndTime->format('H:i:s'))
                                ->whereRaw("COALESCE(end_time, ADDTIME(start_time, '01:00:00')) > ?", [$startCarbon->format('H:i:s')]);
                        });
                    })
                    ->exists();

                if ($overlappingBookings) {
                    throw new \RuntimeException('Room no longer available at this time - overlapping booking exists');
                }
            }

            if ($staffId) {
                // Check if staff is WITH a patient at this specific time slot
                // Staff is with patient for first 30 minutes only (not the full hour)
                // Staff is with patient if: booking.start_time <= timeSlot AND (booking.start_time + 30min) > timeSlot
                $slotTime = Carbon::parse($date . ' ' . $startTime)->format('H:i:s');

                $staffWithPatient = TherapySession::where('staff_id', $staffId)
                    ->where('date', $date)
                    ->where('start_time', '<=', $slotTime)
                    ->whereRaw("ADDTIME(start_time, '00:30:00') > ?", [$slotTime])
                    ->where('status', '!=', 'Cancelled')
                    ->exists();

                if ($staffWithPatient) {
                    throw new \RuntimeException('Staff is currently with a patient at this time');
                }
            }

            // Create booking
            $booking = TherapySession::create([
                'patient_id' => $patientId,
                'patient_name' => $data['patient_name'] ?? null,
                'emr_patient_id' => $data['emr_patient_id'] ?? $data['patient_id'] ?? null, // Legacy EMR ID
                'phone' => $data['phone'] ?? null,
                'therapy_type' => $data['therapy_type'] ?? null, // Legacy field
                'treatment_id' => $treatmentId,
                'staff_id' => $staffId,
                'room_id' => $roomId,
                'branch_id' => $branchId,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'Planned',
                'whatsapp_status' => 'No response',
                'notes' => $data['notes'] ?? null,
            ]);

            // UPDATE AVAILABILITY GRID (Sheet2 maintenance)
            // Mark 2 consecutive slots as 'Booked' (1-hour session = 2 x 30-min slots)
            if ($roomId) {
                $this->updateAvailabilityGrid($branchId, $roomId, $date, $startTime, $booking->id);
            }

            return $booking;
        });
    }

    /**
     * Cancel a booking and free up the slot
     * 
     * @param int $bookingId
     * @param string|null $reason
     * @return bool
     */
    public function cancelBooking(int $bookingId, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($bookingId, $reason) {
            $booking = TherapySession::lockForUpdate()->find($bookingId);
            
            if (!$booking) {
                return false;
            }

            // Update booking status
            $booking->status = 'Cancelled';
            if ($reason) {
                $booking->notes = ($booking->notes ? $booking->notes . "\n" : '') . "Cancelled: $reason";
            }
            $booking->save();

            // Free up availability grid slots
            if ($booking->room_id) {
                RoomAvailabilitySlot::where('booking_id', $bookingId)
                    ->update([
                        'status' => 'Available',
                        'booking_id' => null,
                    ]);
            }

            return true;
        });
    }

    /**
     * Update availability grid when booking is created
     * 
     * @param int $branchId
     * @param int $roomId
     * @param string $date YYYY-MM-DD
     * @param string $startTime HH:MM
     * @param int $bookingId
     */
    private function updateAvailabilityGrid(int $branchId, int $roomId, string $date, string $startTime, int $bookingId): void
    {
        $startCarbon = Carbon::parse($date . ' ' . $startTime);
        $slot1Time = $startCarbon->format('H:i:s');
        $slot2Time = $startCarbon->copy()->addMinutes(30)->format('H:i:s');

        // Update or insert slot 1
        RoomAvailabilitySlot::updateOrCreate(
            [
                'room_id' => $roomId,
                'date' => $date,
                'time_slot' => $slot1Time,
            ],
            [
                'branch_id' => $branchId,
                'status' => 'Booked',
                'booking_id' => $bookingId,
            ]
        );

        // Update or insert slot 2
        RoomAvailabilitySlot::updateOrCreate(
            [
                'room_id' => $roomId,
                'date' => $date,
                'time_slot' => $slot2Time,
            ],
            [
                'branch_id' => $branchId,
                'status' => 'Booked',
                'booking_id' => $bookingId,
            ]
        );
    }

    /**
     * Get patient gender from patient_id or data
     * 
     * @param int|null $patientId
     * @param array<string, mixed> $data
     * @return string
     */
    private function getPatientGender(?int $patientId, array $data): string
    {
        if ($patientId) {
            $patient = Patient::find($patientId);
            if ($patient) {
                return $patient->gender;
            }
        }

        // Try to get from data
        if (isset($data['patient_gender'])) {
            return $data['patient_gender'];
        }

        // Default fallback (should not happen in production)
        return 'Male';
    }
}
