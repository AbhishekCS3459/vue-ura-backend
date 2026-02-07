<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\TreatmentSyncService;
use App\Models\Treatment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class TreatmentController extends Controller
{
    /**
     * Get all treatments - client API format (ServiceName, NoofSessions, id)
     * GET /api/treatments
     */
    public function index(): JsonResponse
    {
        $treatments = Treatment::select('id', 'external_id', 'name', 'noof_sessions')
            ->orderBy('name')
            ->get();

        $results = $treatments->map(function ($t) {
            return [
                'id' => $t->id,
                'external_id' => $t->external_id,
                'ServiceName' => $t->name,
                'NoofSessions' => (string) ($t->noof_sessions ?? '1'),
            ];
        });

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Create treatment (Super Admin + Branch Manager)
     * POST /api/treatments
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ServiceName' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'NoofSessions' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $name = $request->ServiceName ?? $request->name;
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'errors' => ['ServiceName' => ['Service name is required']],
            ], 422);
        }

        $treatment = Treatment::create([
            'name' => $name,
            'noof_sessions' => $request->NoofSessions ?? '1',
        ]);

        return response()->json([
            'success' => true,
            'results' => [
                [
                    'id' => $treatment->id,
                    'external_id' => $treatment->external_id,
                    'ServiceName' => $treatment->name,
                    'NoofSessions' => (string) ($treatment->noof_sessions ?? '1'),
                ],
            ],
            'message' => 'Treatment created successfully',
        ], 201);
    }

    /**
     * Update treatment (Super Admin + Branch Manager)
     * PUT /api/treatments/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json([
                'success' => false,
                'message' => 'Treatment not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'ServiceName' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'NoofSessions' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [];
        $name = $request->ServiceName ?? $request->name;
        if ($name !== null) {
            $data['name'] = $name;
        }
        if ($request->has('NoofSessions')) {
            $data['noof_sessions'] = $request->NoofSessions ?? '1';
        }

        $treatment->update($data);

        return response()->json([
            'success' => true,
            'results' => [
                [
                    'id' => $treatment->id,
                    'external_id' => $treatment->external_id,
                    'ServiceName' => $treatment->name,
                    'NoofSessions' => (string) ($treatment->noof_sessions ?? '1'),
                ],
            ],
            'message' => 'Treatment updated successfully',
        ]);
    }

    /**
     * Delete treatment (Super Admin + Branch Manager)
     * DELETE /api/treatments/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json([
                'success' => false,
                'message' => 'Treatment not found',
            ], 404);
        }

        $treatment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Treatment deleted successfully',
        ]);
    }

    /**
     * Sync treatments from client API (APIServiceMaster.jsp)
     * POST /api/treatments/sync
     */
    public function syncFromClient(TreatmentSyncService $syncService): JsonResponse
    {
        try {
            $result = $syncService->sync();

            return response()->json([
                'success' => true,
                'message' => 'Treatments synced successfully',
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'newTreatments' => $result['newTreatments'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
