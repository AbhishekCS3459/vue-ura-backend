<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\InactivePatientService;
use App\Models\InactivePatient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class InactivePatientController extends Controller
{
    public function __construct(
        private readonly InactivePatientService $inactivePatientService,
    ) {
    }

    /**
     * Get all inactive patients with pagination
     * GET /api/inactive-patients
     * Can fetch directly from bookings or from inactive_patients table
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', 'string', 'in:Follow-up,Did not reply,Did not pick up,Next,Ask for callback'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sync' => ['nullable', 'string', 'in:true,false,1,0'], // Accept string "true"/"false" or "1"/"0"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // If sync is requested, sync from bookings first
        $syncValue = $request->get('sync', false);
        $shouldSync = $syncValue === 'true' || $syncValue === '1' || $syncValue === true || $syncValue === 1;
        
        if ($shouldSync) {
            $branchId = $request->has('branch_id') ? (int) $request->branch_id : null;
            $dateFrom = $request->has('date_from') ? $request->date_from : null;
            $dateTo = $request->has('date_to') ? $request->date_to : null;
            $this->inactivePatientService->syncInactivePatients($branchId, $dateFrom, $dateTo);
        }

        $query = InactivePatient::query();

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $patients = $query->orderBy('days_since_last_session', 'desc')
            ->orderBy('last_session_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $patients->currentPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
                'data' => $patients->map(fn ($patient) => $this->formatInactivePatient($patient)),
            ],
        ]);
    }

    /**
     * Sync inactive patients from therapy sessions (bookings)
     * POST /api/inactive-patients/sync
     * Uses the same filters as the bookings API
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $branchId = $request->has('branch_id') ? (int) $request->branch_id : null;
            $dateFrom = $request->has('date_from') ? $request->date_from : null;
            $dateTo = $request->has('date_to') ? $request->date_to : null;
            
            $synced = $this->inactivePatientService->syncInactivePatients($branchId, $dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'message' => "Synced {$synced} inactive patients",
                'data' => [
                    'synced_count' => $synced,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync inactive patients: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update inactive patient status
     * PUT /api/inactive-patients/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:Follow-up,Did not reply,Did not pick up,Next,Ask for callback'],
            'next_follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $nextFollowUpDate = $request->has('next_follow_up_date') ? $request->next_follow_up_date : null;
            $patient = $this->inactivePatientService->updateStatus(
                $id,
                $request->status,
                $nextFollowUpDate
            );

            return response()->json([
                'success' => true,
                'data' => $this->formatInactivePatient($patient),
                'message' => 'Status updated successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update inactive patient action
     * PUT /api/inactive-patients/{id}/action
     */
    public function updateAction(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required', 'string', 'in:None,Message sent,Follow up call made'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $patient = $this->inactivePatientService->updateAction($id, $request->action);

            return response()->json([
                'success' => true,
                'data' => $this->formatInactivePatient($patient),
                'message' => 'Action updated successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update action: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send reminder to inactive patient
     * POST /api/inactive-patients/{id}/send-reminder
     */
    public function sendReminder(Request $request, int $id): JsonResponse
    {
        try {
            $patient = $this->inactivePatientService->updateAction($id, 'Message sent');

            return response()->json([
                'success' => true,
                'data' => $this->formatInactivePatient($patient),
                'message' => 'Reminder sent successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format inactive patient for JSON response
     */
    private function formatInactivePatient(InactivePatient $patient): array
    {
        return [
            'id' => (string) $patient->id,
            'name' => $patient->name,
            'phone' => $patient->phone ?? '',
            'lastSessionDate' => $patient->last_session_date?->format('Y-m-d') ?? '',
            'daysSinceLastSession' => $patient->days_since_last_session,
            'lastTherapist' => $patient->last_therapist ?? 'Unknown',
            'lastTherapistId' => $patient->last_therapist_id ? (string) $patient->last_therapist_id : '',
            'status' => $patient->status ?? 'Follow-up',
            'lastAction' => $patient->last_action ?? 'None',
            'lastStatusUpdate' => $patient->last_status_update?->format('Y-m-d') ?? '',
            'nextFollowUpDate' => $patient->next_follow_up_date?->format('Y-m-d'),
        ];
    }
}
