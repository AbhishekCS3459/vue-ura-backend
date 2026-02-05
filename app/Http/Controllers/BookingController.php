<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SchedulingService;
use App\Application\Services\BookingService;
use App\Models\TherapySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BookingController extends Controller
{
    public function __construct(
        private readonly SchedulingService $schedulingService,
        private readonly BookingService $bookingService,
    ) {
    }

    /**
     * Get available staff for branch and treatment
     * GET /api/bookings/available-staff
     */
    public function getAvailableStaff(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'treatment_id' => ['required', 'integer', 'exists:treatments,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $staff = $this->schedulingService->getAvailableStaffForTreatment(
                (int) $data['branch_id'],
                (int) $data['treatment_id']
            );

            return response()->json([
                'success' => true,
                'data' => $staff,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Find next available slot for booking
     * POST /api/bookings/find-available-slot
     */
    public function findAvailableSlot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'patient_gender' => ['required', 'string', 'in:Male,Female'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $slot = $this->schedulingService->findAvailableSlot(
                $data['branch_id'],
                $data['treatment_id'],
                $data['patient_gender'],
                $data['preferred_date'] ?? null,
                $data['preferred_time'] ?? null
            );

            if ($slot === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available slots found in the next 30 days',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $slot,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create a new booking
     * POST /api/bookings
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'room_id' => ['nullable', 'integer', 'exists:branch_rooms,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'phone' => ['required_without:patient_id', 'string'],
            'patient_name' => ['nullable', 'string'],
            'patient_gender' => ['required_without:patient_id', 'string', 'in:Male,Female'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();
            $booking = $this->bookingService->createBooking($data);

            return response()->json([
                'success' => true,
                'data' => $this->formatBooking($booking),
                'message' => 'Booking created successfully',
            ], 201);
        } catch (\RuntimeException $e) {
            // Check if it's a slot availability error
            if (str_contains($e->getMessage(), 'no longer available') || 
                str_contains($e->getMessage(), 'full capacity')) {
                // Try to find next available slot
                try {
                    $nextSlot = $this->schedulingService->findAvailableSlot(
                        $data['branch_id'],
                        $data['treatment_id'],
                        $data['patient_gender'] ?? 'Male',
                        $data['date'] ?? null,
                        $data['start_time'] ?? null
                    );

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'data' => [
                            'next_available_slot' => $nextSlot,
                        ],
                    ], 409);
                } catch (\Exception $ex) {
                    // Fall through to error response
                }
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all bookings with filters
     * GET /api/bookings
     */
    public function index(Request $request): JsonResponse
    {
        $query = TherapySession::with(['patient', 'treatment', 'staff', 'room', 'branch']);

        // Apply filters
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $bookings = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'data' => $bookings->map(fn ($booking) => $this->formatBooking($booking)),
            ],
        ]);
    }

    /**
     * Get booking by ID
     * GET /api/bookings/{id}
     */
    public function show(int $id): JsonResponse
    {
        $booking = TherapySession::with(['patient', 'treatment', 'staff', 'room', 'branch'])
            ->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatBooking($booking),
        ]);
    }

    /**
     * Update booking
     * PUT /api/bookings/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $booking = TherapySession::find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'string', 'in:Planned,Completed,No-show,Conflict,Cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $this->formatBooking($booking->fresh()),
            'message' => 'Booking updated successfully',
        ]);
    }

    /**
     * Cancel a booking
     * PUT /api/bookings/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $this->bookingService->cancelBooking($id, $validator->validated()['reason'] ?? null);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => 'Cancelled',
            ],
            'message' => 'Booking cancelled successfully',
        ]);
    }

    /**
     * Format booking for JSON response
     */
    private function formatBooking(TherapySession $booking): array
    {
        return [
            'id' => $booking->id,
            'patient' => $booking->patient ? [
                'id' => $booking->patient->id,
                'name' => $booking->patient->name,
                'gender' => $booking->patient->gender,
            ] : [
                'name' => $booking->patient_name,
                'emr_id' => $booking->emr_patient_id,
            ],
            'treatment' => $booking->treatment ? [
                'id' => $booking->treatment->id,
                'name' => $booking->treatment->name,
            ] : null,
            'staff' => $booking->staff ? [
                'id' => $booking->staff->id,
                'name' => $booking->staff->name,
            ] : null,
            'room' => $booking->room ? [
                'id' => $booking->room->id,
                'name' => $booking->room->name,
            ] : null,
            'branch' => $booking->branch ? [
                'id' => $booking->branch->id,
                'name' => $booking->branch->name,
            ] : null,
            'date' => $booking->date->format('Y-m-d'),
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'status' => $booking->status,
            'whatsapp_status' => $booking->whatsapp_status,
            'notes' => $booking->notes,
        ];
    }
}
