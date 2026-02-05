<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

final class RoomController extends Controller
{
    /**
     * Get all rooms for a branch
     * GET /api/branches/{branchId}/rooms
     */
    public function index(int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $rooms = BranchRoom::where('branch_id', $branchId)
            ->with('treatments')
            ->get()
            ->sortBy(function ($room) {
                // Natural sort: extract number from room name for proper ordering
                // "Room 1" -> 1, "Room 2" -> 2, "Room 10" -> 10
                if (preg_match('/(\d+)/', $room->name, $matches)) {
                    return (int) $matches[1];
                }
                // If no number found, use name as fallback
                return $room->name;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rooms->map(function ($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'branch_id' => $room->branch_id,
                    'gender' => $room->gender,
                    'treatments' => $room->treatments->map(function ($treatment) {
                        return [
                            'id' => $treatment->id,
                            'name' => $treatment->name,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * Create a new room
     * POST /api/branches/{branchId}/rooms
     */
    public function store(Request $request, int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Unisex'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $room = BranchRoom::create([
            'name' => $request->name,
            'branch_id' => $branchId,
            'gender' => $request->gender ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $room->id,
                'name' => $room->name,
                'branch_id' => $room->branch_id,
                'gender' => $room->gender,
            ],
            'message' => 'Room created successfully',
        ], 201);
    }

    /**
     * Update room
     * PUT /api/rooms/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $room = BranchRoom::find($id);

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Unisex'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $room->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $room->id,
                'name' => $room->name,
                'branch_id' => $room->branch_id,
                'gender' => $room->gender,
            ],
            'message' => 'Room updated successfully',
        ]);
    }

    /**
     * Delete room
     * DELETE /api/rooms/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $room = BranchRoom::find($id);

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found',
            ], 404);
        }

        // Check if room has any bookings
        $hasBookings = DB::table('therapy_sessions')
            ->where('room_id', $id)
            ->where('status', '!=', 'Cancelled')
            ->exists();

        if ($hasBookings) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete room with existing bookings',
            ], 400);
        }

        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room deleted successfully',
        ]);
    }

    /**
     * Sync room treatment assignments for a branch
     * POST /api/branches/{branchId}/room-treatment-assignments
     */
    public function syncAssignments(Request $request, int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'assignments' => ['required', 'array'],
            'assignments.*.treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'assignments.*.room_id' => ['required', 'integer', 'exists:branch_rooms,id'],
            'assignments.*.assigned' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify all rooms belong to this branch
        $roomIds = collect($request->assignments)->pluck('room_id')->unique();
        $rooms = BranchRoom::whereIn('id', $roomIds)->where('branch_id', $branchId)->pluck('id');
        
        if ($rooms->count() !== $roomIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Some rooms do not belong to this branch',
            ], 400);
        }

        DB::transaction(function () use ($request, $branchId) {
            // Get all assignments for this branch's rooms
            $roomIds = collect($request->assignments)->pluck('room_id')->unique();
            
            // Delete all existing assignments for these rooms
            DB::table('room_treatment_assignments')
                ->whereIn('room_id', $roomIds)
                ->delete();

            // Insert new assignments (only where assigned = true)
            $assignmentsToInsert = collect($request->assignments)
                ->filter(fn($assignment) => $assignment['assigned'] === true)
                ->map(fn($assignment) => [
                    'treatment_id' => $assignment['treatment_id'],
                    'room_id' => $assignment['room_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->toArray();

            if (!empty($assignmentsToInsert)) {
                DB::table('room_treatment_assignments')->insert($assignmentsToInsert);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Room treatment assignments synced successfully',
        ]);
    }
}
