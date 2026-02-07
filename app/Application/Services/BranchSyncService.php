<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\Branch;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class BranchSyncService
{
    private const LOCATIONS_ENDPOINT = 'APILocation.jsp';

    /**
     * Default opening hours for newly synced branches (matches BranchController format).
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

    /**
     * Fetch branch locations from client API.
     *
     * @return array<int, array{id: string, state_name: string, LocationName: string}>
     *
     * @throws \RuntimeException
     */
    public function fetchFromClientApi(): array
    {
        $baseUrl = config('services.vecura.base_url', 'http://182.79.166.132:8081/VeCura/jsp/API');
        $url = rtrim($baseUrl, '/') . '/' . self::LOCATIONS_ENDPOINT;

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
     * Sync branches from client API.
     * - Uses LocationName as name, state_name as city, id as external_id
     * - Creates new branches or updates existing ones matched by external_id
     * - Preserves our auto-increment id; only updates name and city for minimal change
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function sync(): array
    {
        $locations = $this->fetchFromClientApi();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $defaultHours = $this->getDefaultOpeningHours();

        foreach ($locations as $loc) {
            $externalId = $loc['id'] ?? null;
            $name = $loc['LocationName'] ?? '';
            $city = $loc['state_name'] ?? '';

            if (empty($externalId) || empty($name)) {
                $skipped++;
                continue;
            }

            $branch = Branch::where('external_id', $externalId)->first();

            if ($branch) {
                $branch->update([
                    'name' => $name,
                    'city' => $city,
                ]);
                $updated++;
            } else {
                Branch::create([
                    'external_id' => $externalId,
                    'name' => $name,
                    'city' => $city,
                    'opening_hours' => $defaultHours,
                    'is_open' => true,
                ]);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Replace: delete all branches that have external_id (synced from client), then re-sync.
     * Branches without external_id (our original ones) are kept unless --replace-all is used.
     *
     * @param bool $replaceAll If true, deletes ALL branches and re-syncs from client only
     */
    public function syncReplace(bool $replaceAll = false): array
    {
        $query = Branch::query();
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
