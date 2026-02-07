<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoomAvailabilitySlot;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class PruneOldAvailability extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'availability:prune-old
                            {--days=0 : Delete slots older than N days (default: 0 = today and earlier)}';

    /**
     * The console command description.
     */
    protected $description = 'Prune old availability data: delete past room_availability_slots and remove past date keys from staff availability JSON. Runs daily via cron.';

    public function handle(): int
    {
        $daysAgo = (int) $this->option('days');
        $cutoffDate = Carbon::today()->subDays($daysAgo);

        $this->info("Pruning availability data before {$cutoffDate->toDateString()}...");

        // 1. Delete past room_availability_slots
        $deletedSlots = RoomAvailabilitySlot::where('date', '<', $cutoffDate)->delete();
        $this->info("  Deleted {$deletedSlots} room availability slots.");

        // 2. Remove past date keys from staff availability JSON
        $staffUpdated = 0;
        $staff = Staff::all();

        foreach ($staff as $s) {
            $availability = $s->availability ?? [];
            if (!is_array($availability)) {
                continue;
            }

            $changed = false;
            foreach (array_keys($availability) as $key) {
                // Only remove keys that look like dates (YYYY-MM-DD)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                    $keyDate = Carbon::parse($key);
                    if ($keyDate->lt($cutoffDate)) {
                        unset($availability[$key]);
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $s->availability = $availability;
                $s->save();
                $staffUpdated++;
            }
        }

        $this->info("  Updated {$staffUpdated} staff availability records (removed past dates).");
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
