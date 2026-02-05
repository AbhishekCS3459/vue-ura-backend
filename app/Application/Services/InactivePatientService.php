<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\InactivePatient;
use App\Models\TherapySession;
use App\Models\Patient;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class InactivePatientService
{
    /**
     * Calculate inactive patients from therapy sessions (bookings)
     * Inactive patients are those who have bookings with status != "Completed"
     * Uses the same query logic as the bookings API
     * 
     * @param int|null $branchId Filter by branch
     * @param string|null $dateFrom Optional date from filter
     * @param string|null $dateTo Optional date to filter
     * @return array Array of inactive patient data
     */
    public function calculateInactivePatients(?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        // Query bookings (therapy_sessions) with status != "Completed"
        // Same logic as BookingController::index
        $query = TherapySession::with(['patient', 'staff'])
            ->whereNotNull('patient_id')
            ->where('status', '!=', 'Completed')
            ->where('status', '!=', 'Cancelled');

        // Apply filters (same as bookings API)
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($dateFrom !== null) {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('date', '<=', $dateTo);
        }

        // Get all non-completed bookings
        $bookings = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $result = [];
        $processedPatients = []; // Track processed patient-branch combinations

        foreach ($bookings as $booking) {
            $key = $booking->patient_id . '_' . $booking->branch_id;
            
            // Skip if we've already processed this patient-branch combination
            // We only want one entry per patient-branch, using their most recent non-completed booking
            if (isset($processedPatients[$key])) {
                continue;
            }
            $processedPatients[$key] = true;

            // Get patient info
            $patient = $booking->patient;
            if (!$patient) {
                continue;
            }

            $lastSessionDate = Carbon::parse($booking->date);
            $daysSince = (int) Carbon::now()->diffInDays($lastSessionDate, false);

            // Get last therapist info
            $lastStaff = $booking->staff;
            $lastTherapistName = $lastStaff ? $lastStaff->name : 'Unknown';
            $lastTherapistId = $lastStaff ? $lastStaff->id : null;

            $result[] = [
                'branch_id' => $booking->branch_id,
                'patient_id' => $booking->patient_id,
                'name' => $patient->name,
                'phone' => $patient->phone ?? '',
                'last_session_date' => $lastSessionDate->format('Y-m-d'),
                'days_since_last_session' => $daysSince,
                'last_therapist' => $lastTherapistName,
                'last_therapist_id' => $lastTherapistId,
                'status' => 'Follow-up', // Default status
                'last_action' => 'None', // Default action
                'last_status_update' => null,
                'next_follow_up_date' => null,
            ];
        }

        return $result;
    }

    /**
     * Sync inactive patients to database (upsert)
     * 
     * @param int|null $branchId Filter by branch
     * @param string|null $dateFrom Optional date from filter
     * @param string|null $dateTo Optional date to filter
     * @return int Number of inactive patients synced
     */
    public function syncInactivePatients(?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $inactivePatients = $this->calculateInactivePatients($branchId, $dateFrom, $dateTo);

        $synced = 0;

        foreach ($inactivePatients as $data) {
            // Check if record already exists
            $existing = InactivePatient::where('patient_id', $data['patient_id'])
                ->where('branch_id', $data['branch_id'])
                ->first();

            if ($existing) {
                // Update existing record
                $existing->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'last_session_date' => $data['last_session_date'],
                    'days_since_last_session' => $data['days_since_last_session'],
                    'last_therapist' => $data['last_therapist'],
                    'last_therapist_id' => $data['last_therapist_id'],
                    // Don't overwrite status, action, or follow-up date if they exist
                ]);
            } else {
                // Create new record
                InactivePatient::create($data);
            }

            $synced++;
        }

        return $synced;
    }

    /**
     * Update inactive patient status
     * 
     * @param int $id Inactive patient ID
     * @param string $status New status
     * @param string|null $nextFollowUpDate Optional next follow-up date
     * @return InactivePatient
     * @throws \RuntimeException
     */
    public function updateStatus(int $id, string $status, ?string $nextFollowUpDate = null): InactivePatient
    {
        $validStatuses = ['Follow-up', 'Did not reply', 'Did not pick up', 'Next', 'Ask for callback'];
        
        if (!in_array($status, $validStatuses)) {
            throw new \RuntimeException("Invalid status: {$status}");
        }

        $inactivePatient = InactivePatient::findOrFail($id);

        $updateData = [
            'status' => $status,
            'last_status_update' => Carbon::now(),
        ];

        if ($nextFollowUpDate !== null) {
            $updateData['next_follow_up_date'] = Carbon::parse($nextFollowUpDate)->format('Y-m-d');
        }

        $inactivePatient->update($updateData);

        return $inactivePatient->fresh();
    }

    /**
     * Update inactive patient action
     * 
     * @param int $id Inactive patient ID
     * @param string $action New action
     * @return InactivePatient
     * @throws \RuntimeException
     */
    public function updateAction(int $id, string $action): InactivePatient
    {
        $validActions = ['None', 'Message sent', 'Follow up call made'];
        
        if (!in_array($action, $validActions)) {
            throw new \RuntimeException("Invalid action: {$action}");
        }

        $inactivePatient = InactivePatient::findOrFail($id);

        $inactivePatient->update([
            'last_action' => $action,
            'last_status_update' => Carbon::now(),
        ]);

        return $inactivePatient->fresh();
    }
}
