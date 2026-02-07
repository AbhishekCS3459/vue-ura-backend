<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\TreatmentSyncService;
use Illuminate\Console\Command;

final class SyncTreatmentsFromClient extends Command
{
    protected $signature = 'treatments:sync-from-client
                            {--replace : Delete previously synced treatments before re-syncing}
                            {--replace-all : Delete ALL treatments and sync only from client}
                            {--force : Skip confirmation when using --replace-all}';

    protected $description = 'Sync treatments/services from client API (APIServiceMaster.jsp). Maps ServiceName→name, id→external_id, NoofSessions→noof_sessions.';

    public function handle(TreatmentSyncService $syncService): int
    {
        $replaceAll = $this->option('replace-all');
        $replace = $this->option('replace');

        if ($replaceAll && !$this->option('force')) {
            if (!$this->confirm('This will DELETE ALL treatments. Continue?')) {
                return Command::FAILURE;
            }
        }

        try {
            if ($replaceAll) {
                $result = $syncService->syncReplace(replaceAll: true);
                $this->info("Deleted {$result['deleted']} treatments.");
            } elseif ($replace) {
                $result = $syncService->syncReplace(replaceAll: false);
                $this->info("Deleted {$result['deleted']} previously synced treatments.");
            } else {
                $result = $syncService->sync();
            }

            $this->info("Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}");
            $this->info('Treatment sync completed successfully.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
