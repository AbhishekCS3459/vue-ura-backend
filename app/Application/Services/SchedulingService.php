<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\Branch;
use App\Models\BranchRoom;
use App\Models\Staff;
use App\Models\Treatment;
use App\Models\RoomAvailabilitySlot;
use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SchedulingService
{
    /**
     * Find next available slot for booking
     * 
     * @param int $branchId
     * @param int $treatmentId
     * @param string $patientGender 'Male' or 'Female'
     * @param string|null $preferredDate YYYY-MM-DD format
     * @param string|null $preferredTime HH:MM format
     * @return array{date: string, start_time: string, end_time: string, staff: array, room: array, treatment: array}|null
     * @throws \RuntimeException
     */
    public function findAvailableSlot(
        int $branchId,
        int $treatmentId,
        string $patientGender,
        ?string $preferredDate = null,
        ?string $preferredTime = null
    ): ?array {
        // STEP 1: Validate Input
        $branch = Branch::find($branchId);
        if (!$branch) {
            throw new \RuntimeException('Branch not found');
        }

        if (!$branch->is_open) {
            throw new \RuntimeException('Branch is currently closed');
        }

        $treatment = Treatment::find($treatmentId);
        if (!$treatment) {
            throw new \RuntimeException('Treatment not found');
        }

        if (!in_array($patientGender, ['Male', 'Female'])) {
            throw new \RuntimeException('Invalid patient gender. Must be Male or Female');
        }

        // STEP 2: Find Compatible Room IDs (Sheet3 Logic)
        $compatibleRooms = $this->findCompatibleRooms($branchId, $treatmentId, $patientGender);
        
        if (empty($compatibleRooms)) {
            throw new \RuntimeException('No compatible rooms found for this treatment and gender');
        }

        // Extract just the room IDs for the grid check
        $compatibleRoomIds = array_column($compatibleRooms, 'id');

        // STEP 3: Find Available Staff
        $compatibleStaff = $this->findCompatibleStaff($branchId, $treatmentId);
        
        if (empty($compatibleStaff)) {
            throw new \RuntimeException('No available staff found for this treatment');
        }

        // STEP 4: Get Branch Operating Hours
        $currentDate = $preferredDate ? Carbon::parse($preferredDate) : Carbon::today();
        $initialTime = $preferredTime ? Carbon::parse($preferredTime)->format('H:i') : Carbon::now()->format('H:i');

        // STEP 5: Scan Availability Grid (Sheet2 Logic)
        $maxDays = 30;
        $daysScanned = 0;

        while ($daysScanned < $maxDays) {
            $dayOfWeek = strtolower($currentDate->format('l')); // monday, tuesday, etc.
            $openingHours = $branch->opening_hours[$dayOfWeek] ?? null;

            if ($openingHours === null) {
                // Branch closed on this day, move to next day
                $currentDate->addDay();
                $daysScanned++;
                $initialTime = $openingHours['open'] ?? '06:00';
                continue;
            }

            $openTime = $openingHours['open'] ?? '06:00';
            $closeTime = $openingHours['close'] ?? '20:00';

            // Start scanning from initial time or opening time, whichever is later
            $slotTime = max($initialTime, $openTime);
            $slotCarbon = Carbon::parse($currentDate->format('Y-m-d') . ' ' . $slotTime);

            while ($slotCarbon->format('H:i') < $closeTime) {
                $endSlotTime = $slotCarbon->copy()->addHour();

                // Check if 1-hour session fits before closing time
                if ($endSlotTime->format('H:i') > $closeTime) {
                    break;
                }

                // Check room availability in grid (Sheet2 logic)
                $availableRooms = $this->checkRoomAvailabilityInGrid(
                    $compatibleRoomIds,
                    $currentDate->format('Y-m-d'),
                    $slotCarbon->format('H:i')
                );

                // Check staff capacity
                $availableStaff = $this->checkStaffCapacity(
                    $compatibleStaff,
                    $currentDate->format('Y-m-d'),
                    $slotCarbon->format('H:i')
                );

                // If both room and staff available, return slot
                if (!empty($availableRooms) && !empty($availableStaff)) {
                    $selectedRoom = $availableRooms[0];
                    $selectedStaff = $availableStaff[0];

                    // Double-check gender compatibility (safety check)
                    if ($selectedRoom['gender'] !== 'Unisex' && $selectedRoom['gender'] !== $patientGender) {
                        // Should never happen, but safety check
                        $slotCarbon->addMinutes(30);
                        continue;
                    }

                    return [
                        'date' => $currentDate->format('Y-m-d'),
                        'start_time' => $slotCarbon->format('H:i'),
                        'end_time' => $endSlotTime->format('H:i'),
                        'staff' => [
                            'id' => $selectedStaff['id'],
                            'name' => $selectedStaff['name'],
                        ],
                        'room' => [
                            'id' => $selectedRoom['id'],
                            'name' => $selectedRoom['name'],
                        ],
                        'treatment' => [
                            'id' => $treatment->id,
                            'name' => $treatment->name,
                        ],
                    ];
                }

                // Move to next 30-minute slot
                $slotCarbon->addMinutes(30);
            }

            // Move to next day
            $currentDate->addDay();
            $daysScanned++;
            $initialTime = $openTime;
        }

        return null; // No slot found
    }

    /**
     * Find compatible rooms based on gender and treatment (Sheet3 Logic)
     * 
     * @param int $branchId
     * @param int $treatmentId
     * @param string $patientGender
     * @return array<array{id: int, name: string, gender: string}>
     */
    private function findCompatibleRooms(int $branchId, int $treatmentId, string $patientGender): array
    {
        return BranchRoom::query()
            ->join('room_treatment_assignments', 'branch_rooms.id', '=', 'room_treatment_assignments.room_id')
            ->where('branch_rooms.branch_id', $branchId)
            ->where('room_treatment_assignments.treatment_id', $treatmentId)
            ->where(function ($query) use ($patientGender) {
                $query->where('branch_rooms.gender', 'Unisex')
                    ->orWhere('branch_rooms.gender', $patientGender);
            })
            ->select('branch_rooms.id', 'branch_rooms.name', 'branch_rooms.gender')
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'gender' => $room->gender,
                ];
            })
            ->toArray();
    }

    /**
     * Get available staff for branch and treatment (public method for API)
     * 
     * @param int $branchId
     * @param int $treatmentId
     * @return array<array{id: int, name: string, availability: array}>
     */
    public function getAvailableStaffForTreatment(int $branchId, int $treatmentId): array
    {
        return $this->findCompatibleStaff($branchId, $treatmentId);
    }

    /**
     * Find compatible staff based on treatment
     * 
     * @param int $branchId
     * @param int $treatmentId
     * @return array<array{id: int, name: string, availability: array}>
     */
    private function findCompatibleStaff(int $branchId, int $treatmentId): array
    {
        return Staff::query()
            ->join('staff_treatment_assignments', 'staff.id', '=', 'staff_treatment_assignments.staff_id')
            ->where('staff.branch_id', $branchId)
            ->where('staff_treatment_assignments.treatment_id', $treatmentId)
            ->select('staff.id', 'staff.name', 'staff.availability')
            ->get()
            ->map(function ($staff) {
                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'availability' => $staff->availability,
                ];
            })
            ->toArray();
    }

    /**
     * Check room availability in grid (Sheet2 Logic)
     * Checks if 2 consecutive slots are available (1-hour session)
     * 
     * @param array<int> $roomIds
     * @param string $date YYYY-MM-DD
     * @param string $startTime HH:MM
     * @return array<array{id: int, name: string, gender: string}>
     */
    private function checkRoomAvailabilityInGrid(array $roomIds, string $date, string $startTime): array
    {
        if (empty($roomIds)) {
            return [];
        }

        // Calculate second slot time (30 minutes after start)
        $startCarbon = Carbon::parse($date . ' ' . $startTime);
        $slot2Time = $startCarbon->copy()->addMinutes(30)->format('H:i:s');

        // Check if both slots are available for any room
        $availableRooms = DB::table('room_availability_slots as ras1')
            ->join('room_availability_slots as ras2', function ($join) use ($date, $startTime, $slot2Time) {
                $join->on('ras1.room_id', '=', 'ras2.room_id')
                    ->where('ras1.date', '=', $date)
                    ->where('ras1.time_slot', '=', $startTime)
                    ->where('ras1.status', '=', 'Available')
                    ->where('ras2.date', '=', $date)
                    ->where('ras2.time_slot', '=', $slot2Time)
                    ->where('ras2.status', '=', 'Available');
            })
            ->join('branch_rooms', 'ras1.room_id', '=', 'branch_rooms.id')
            ->whereIn('ras1.room_id', $roomIds)
            ->select('branch_rooms.id', 'branch_rooms.name', 'branch_rooms.gender')
            ->distinct()
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'gender' => $room->gender,
                ];
            })
            ->toArray();

        // If no slots exist in grid, check if room is free from bookings
        // This handles cases where grid hasn't been initialized
        if (empty($availableRooms)) {
            foreach ($roomIds as $roomId) {
                $room = BranchRoom::find($roomId);
                if (!$room) {
                    continue;
                }

                // Check if room has any overlapping bookings
                $overlapping = TherapySession::where('room_id', $roomId)
                    ->where('date', $date)
                    ->where(function ($query) use ($date, $startTime) {
                        $startCarbon = Carbon::parse($date . ' ' . $startTime);
                        $endTime = $startCarbon->copy()->addHour()->format('H:i:s');
                        
                        $query->where(function ($q) use ($startTime, $endTime) {
                            $q->where(function ($q2) use ($startTime, $endTime) {
                                $q2->where('start_time', '<', $endTime)
                                    ->where('end_time', '>', $startTime);
                            })
                            ->orWhere('start_time', '=', $startTime);
                        })
                        ->where('status', '!=', 'Cancelled');
                    })
                    ->exists();

                if (!$overlapping) {
                    $availableRooms[] = [
                        'id' => $room->id,
                        'name' => $room->name,
                        'gender' => $room->gender,
                    ];
                }
            }
        }

        return $availableRooms;
    }

    /**
     * Check staff capacity (max 2 patients per hour)
     * 
     * @param array<array{id: int, name: string, availability: array}> $staffList
     * @param string $date YYYY-MM-DD
     * @param string $startTime HH:MM
     * @return array<array{id: int, name: string}>
     */
    private function checkStaffCapacity(array $staffList, string $date, string $startTime): array
    {
        $availableStaff = [];
        $dateCarbon = Carbon::parse($date);
        $dayOfWeek = strtolower($dateCarbon->format('l')); // monday, tuesday, etc.
        $dateStr = $dateCarbon->format('Y-m-d'); // YYYY-MM-DD format

        foreach ($staffList as $staff) {
            $availability = $staff['availability'] ?? [];
            $isAvailable = false;
            
            // Check date-specific availability first (takes priority)
            if (isset($availability[$dateStr]) && is_array($availability[$dateStr])) {
                $dateSpecificTimes = $availability[$dateStr];
                // Check if start time matches any time slot (format: HH:MM)
                $timeMatches = false;
                foreach ($dateSpecificTimes as $timeSlot) {
                    // Remove seconds if present (HH:MM:SS -> HH:MM)
                    $timeSlotFormatted = substr($timeSlot, 0, 5);
                    if ($timeSlotFormatted === $startTime || $timeSlotFormatted === substr($startTime, 0, 5)) {
                        $timeMatches = true;
                        break;
                    }
                }
                $isAvailable = $timeMatches && !empty($dateSpecificTimes);
            }
            
            // If no date-specific availability, check day-based availability
            if (!$isAvailable && isset($availability[$dayOfWeek]) && is_array($availability[$dayOfWeek])) {
                $dayBasedTimes = $availability[$dayOfWeek];
                // Check if start time matches any time slot
                $timeMatches = false;
                foreach ($dayBasedTimes as $timeSlot) {
                    $timeSlotFormatted = substr($timeSlot, 0, 5);
                    if ($timeSlotFormatted === $startTime || $timeSlotFormatted === substr($startTime, 0, 5)) {
                        $timeMatches = true;
                        break;
                    }
                }
                $isAvailable = $timeMatches && !empty($dayBasedTimes);
            }
            
            if (!$isAvailable) {
                continue; // Staff not available at this time
            }

            // Check if staff is WITH a patient at this specific time slot
            // Staff is with patient for first 30 minutes only (not the full hour)
            // Staff is with patient if: booking.start_time <= timeSlot AND (booking.start_time + 30min) > timeSlot
            $slotTime = Carbon::parse($date . ' ' . $startTime)->format('H:i:s');

            $isWithPatient = TherapySession::where('staff_id', $staff['id'])
                ->where('date', $date)
                ->where('start_time', '<=', $slotTime)
                ->whereRaw("ADDTIME(start_time, '00:30:00') > ?", [$slotTime])
                ->where('status', '!=', 'Cancelled')
                ->where('whatsapp_status', '!=', 'Cancelled')
                ->exists();

            // Staff is available if NOT with a patient
            if (!$isWithPatient) {
                $availableStaff[] = [
                    'id' => $staff['id'],
                    'name' => $staff['name'],
                ];
            }
        }

        return $availableStaff;
    }
}
