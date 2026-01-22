<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        $status = $dbStatus === 'connected' ? 'ok' : 'error';

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'database' => $dbStatus,
        ]);
    }
}
