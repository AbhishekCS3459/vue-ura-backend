<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\BranchSyncService;
use Illuminate\Console\Command;

final class SyncBranchesFromClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:sync-from-client
                            {--replace : Delete previously synced branches (with external_id) before re-syncing}
                            {--replace-all : Delete ALL branches and sync only from client API. Cascades to staff, rooms, etc.}
                            {--force : Skip confirmation when using --replace-all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time sync of branches from client API (APILocation.jsp). Maps LocationName→name, state_name→city, id→external_id.';

    public function handle(BranchSyncService $syncService): int
    {
        $replaceAll = $this->option('replace-all');
        $replace = $this->option('replace');

        if ($replaceAll && !$this->option('force')) {
            if (!$this->confirm('This will DELETE ALL branches and related data (staff, rooms, etc.). Continue?')) {
                return Command::FAILURE;
            }
        }

        try {
            if ($replaceAll) {
                $result = $syncService->syncReplace(replaceAll: true);
                $this->info("Deleted {$result['deleted']} branches.");
            } elseif ($replace) {
                $result = $syncService->syncReplace(replaceAll: false);
                $this->info("Deleted {$result['deleted']} previously synced branches.");
            } else {
                $result = $syncService->sync();
            }

            $this->info("Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}");
            $this->info('Branch sync completed successfully.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
