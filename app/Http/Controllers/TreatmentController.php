<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Treatment;
use Illuminate\Http\JsonResponse;

final class TreatmentController extends Controller
{
    /**
     * Get all treatments
     * GET /api/treatments
     */
    public function index(): JsonResponse
    {
        $treatments = Treatment::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $treatments->map(function ($treatment) {
                return [
                    'id' => $treatment->id,
                    'name' => $treatment->name,
                ];
            }),
        ]);
    }
}
