<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BranchController extends Controller
{
    /**
     * Get all branches (filtered by role: branch_manager sees only their branch)
     * GET /api/branches
     */
    public function index(Request $request): JsonResponse
    {
        $dbUser = $request->user() ? User::find($request->user()->id) : null;

        $query = Branch::select('id', 'external_id', 'name', 'city', 'is_open', 'opening_hours')
            ->orderBy('name');

        if ($dbUser && $dbUser->role !== 'super_admin' && $dbUser->branch_id !== null) {
            $query->where('id', $dbUser->branch_id);
        }

        $branches = $query->get();

        return response()->json([
            'success' => true,
            'data' => $branches->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'external_id' => $branch->external_id,
                    'name' => $branch->name,
                    'city' => $branch->city,
                    'is_open' => $branch->is_open,
                    'opening_hours' => $branch->opening_hours,
                ];
            }),
        ]);
    }

    /**
     * Create a new branch
     * POST /api/branches
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_id' => ['nullable', 'string', 'max:50', 'unique:branches,external_id'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'is_open' => ['nullable', 'boolean'],
            'opening_hours' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $branch = Branch::create([
            'external_id' => $request->external_id,
            'name' => $request->name,
            'city' => $request->city,
            'is_open' => $request->is_open ?? true,
            'opening_hours' => $request->opening_hours ?? $this->getDefaultOpeningHours(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $branch->id,
                'external_id' => $branch->external_id,
                'name' => $branch->name,
                'city' => $branch->city,
                'is_open' => $branch->is_open,
                'opening_hours' => $branch->opening_hours,
            ],
            'message' => 'Branch created successfully',
        ], 201);
    }

    /**
     * Get branch by ID (branch_manager can only access their branch)
     * GET /api/branches/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $user = $request->user();
        $dbUser = $user ? User::find($user->id) : null;
        if ($dbUser && $dbUser->role !== 'super_admin' && (int) $dbUser->branch_id !== (int) $id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $branch->id,
                'external_id' => $branch->external_id,
                'name' => $branch->name,
                'city' => $branch->city,
                'is_open' => $branch->is_open,
                'opening_hours' => $branch->opening_hours,
            ],
        ]);
    }

    /**
     * Update branch
     * - Super Admin: can update any branch, any field
     * - Branch Manager: can update ONLY their assigned branch, ONLY opening_hours (timings)
     * PUT /api/branches/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $user = $request->user();
        $dbUser = $user ? User::find($user->id) : null;

        if ($dbUser && $dbUser->role === 'branch_manager') {
            if ((int) $dbUser->branch_id !== (int) $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update timings for your assigned branch',
                ], 403);
            }
            // Branch Manager: only allow opening_hours
            $validator = Validator::make($request->all(), [
                'opening_hours' => ['required', 'array'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $branch->update(['opening_hours' => $request->opening_hours]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $branch->id,
                    'external_id' => $branch->external_id,
                    'name' => $branch->name,
                    'city' => $branch->city,
                    'is_open' => $branch->is_open,
                    'opening_hours' => $branch->opening_hours,
                ],
                'message' => 'Opening hours updated successfully',
            ]);
        }

        // Super Admin: full update
        $validator = Validator::make($request->all(), [
            'external_id' => ['nullable', 'string', 'max:50', 'unique:branches,external_id,' . $id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'is_open' => ['nullable', 'boolean'],
            'opening_hours' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $branch->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $branch->id,
                'external_id' => $branch->external_id,
                'name' => $branch->name,
                'city' => $branch->city,
                'is_open' => $branch->is_open,
                'opening_hours' => $branch->opening_hours,
            ],
            'message' => 'Branch updated successfully',
        ]);
    }

    /**
     * Delete branch (Super Admin only)
     * DELETE /api/branches/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $dbUser = $user ? User::find($user->id) : null;

        if (!$dbUser || $dbUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can delete branches',
            ], 403);
        }

        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully',
        ]);
    }

    /**
     * Get default opening hours
     */
    private function getDefaultOpeningHours(): array
    {
        return [
            'monday' => ['open' => '06:00', 'close' => '20:00'],
            'tuesday' => ['open' => '06:00', 'close' => '20:00'],
            'wednesday' => ['open' => '06:00', 'close' => '20:00'],
            'thursday' => ['open' => '06:00', 'close' => '20:00'],
            'friday' => ['open' => '06:00', 'close' => '20:00'],
            'saturday' => ['open' => '08:00', 'close' => '18:00'],
            'sunday' => null,
        ];
    }
}
