<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class VeCuraProxyController extends Controller
{
    private const VECURA_BASE_URL = 'http://182.79.166.132:8081/VeCura/jsp/API';

    /**
     * Proxy GET requests to VeCura API (avoids CORS when calling from Swagger UI).
     */
    public function proxy(string $endpoint): \Illuminate\Http\JsonResponse
    {
        $url = self::VECURA_BASE_URL . '/' . $endpoint;
        $query = request()->query();

        try {
            $response = Http::timeout(15)->get($url, $query);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Upstream request failed',
                    'status' => $response->status(),
                ], $response->status());
            }

            return response()->json($response->json());
        } catch (RequestException $e) {
            return response()->json([
                'error' => 'Failed to reach VeCura API',
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
