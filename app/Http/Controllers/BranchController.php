<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BranchController extends Controller
{
    /**
     * Get all branches
     * GET /api/branches
     */
    public function index(): JsonResponse
    {
        $branches = Branch::select('id', 'name', 'city', 'is_open', 'opening_hours')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches->map(function ($branch) {
                return [
                    'id' => $branch->id,
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
            'name' => $request->name,
            'city' => $request->city,
            'is_open' => $request->is_open ?? true,
            'opening_hours' => $request->opening_hours ?? $this->getDefaultOpeningHours(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'city' => $branch->city,
                'is_open' => $branch->is_open,
                'opening_hours' => $branch->opening_hours,
            ],
            'message' => 'Branch created successfully',
        ], 201);
    }

    /**
     * Get branch by ID
     * GET /api/branches/{id}
     */
    public function show(int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'city' => $branch->city,
                'is_open' => $branch->is_open,
                'opening_hours' => $branch->opening_hours,
            ],
        ]);
    }

    /**
     * Update branch
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

        $validator = Validator::make($request->all(), [
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
                'name' => $branch->name,
                'city' => $branch->city,
                'is_open' => $branch->is_open,
                'opening_hours' => $branch->opening_hours,
            ],
            'message' => 'Branch updated successfully',
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
