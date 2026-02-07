<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\Treatment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class TreatmentSyncService
{
    private const SERVICES_ENDPOINT = 'APIServiceMaster.jsp';

    /**
     * Fetch services from client API.
     *
     * @return array<int, array{id: string, ServiceName: string, NoofSessions: string}>
     *
     * @throws \RuntimeException
     */
    public function fetchFromClientApi(): array
    {
        $baseUrl = config('services.vecura.base_url', 'http://182.79.166.132:8081/VeCura/jsp/API');
        $url = rtrim($baseUrl, '/') . '/' . self::SERVICES_ENDPOINT;

        try {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                throw new \RuntimeException("Client API returned status {$response->status()}");
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            if (!is_array($results)) {
                throw new \RuntimeException('Invalid API response: results is not an array');
            }

            return $results;
        } catch (RequestException $e) {
            throw new \RuntimeException("Failed to reach client API: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Sync treatments from client API.
     * - Uses ServiceName as name, id as external_id, NoofSessions as noof_sessions
     *
     * @return array{created: int, updated: int, skipped: int, newTreatments: array<string>}
     */
    public function sync(): array
    {
        $services = $this->fetchFromClientApi();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $newTreatments = [];

        foreach ($services as $svc) {
            $externalId = $svc['id'] ?? null;
            $name = $svc['ServiceName'] ?? '';
            $noofSessions = (string) ($svc['NoofSessions'] ?? '1');

            if (empty($externalId) || empty($name)) {
                $skipped++;
                continue;
            }

            $treatment = Treatment::where('external_id', $externalId)->first();

            if ($treatment) {
                $treatment->update([
                    'name' => $name,
                    'noof_sessions' => $noofSessions,
                ]);
                $updated++;
            } else {
                Treatment::create([
                    'external_id' => $externalId,
                    'name' => $name,
                    'noof_sessions' => $noofSessions,
                ]);
                $created++;
                $newTreatments[] = $name;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'newTreatments' => $newTreatments];
    }

    /**
     * Replace: delete all treatments with external_id, then re-sync.
     */
    public function syncReplace(bool $replaceAll = false): array
    {
        $query = Treatment::query();
        if (!$replaceAll) {
            $query->whereNotNull('external_id');
        }
        $deleted = $query->delete();

        return array_merge(
            ['deleted' => $deleted],
            $this->sync()
        );
    }
}
