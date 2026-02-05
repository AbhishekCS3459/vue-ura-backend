<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchRoom;
use App\Models\RoomAvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InitializeAvailabilityGrid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grid:initialize 
                            {--days=30 : Number of days to initialize}
                            {--branch= : Specific branch ID (optional)}
                            {--force : Force re-initialization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize room availability grid for the next N days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $branchId = $this->option('branch');
        $force = $this->option('force');

        $this->info("Initializing availability grid for next {$days} days...");

        $branches = $branchId 
            ? Branch::where('id', $branchId)->get()
            : Branch::all();

        if ($branches->isEmpty()) {
            $this->error('No branches found.');
            return Command::FAILURE;
        }

        $totalSlots = 0;

        foreach ($branches as $branch) {
            $this->info("Processing branch: {$branch->name} (ID: {$branch->id})");

            $rooms = BranchRoom::where('branch_id', $branch->id)->get();

            if ($rooms->isEmpty()) {
                $this->warn("  No rooms found for branch {$branch->name}");
                continue;
            }

            $openingHours = $branch->opening_hours ?? [];

            for ($day = 0; $day < $days; $day++) {
                $date = Carbon::today()->addDays($day);
                $dayOfWeek = strtolower($date->format('l')); // monday, tuesday, etc.
                
                $hours = $openingHours[$dayOfWeek] ?? null;

                if ($hours === null) {
                    // Branch closed on this day, mark all slots as unavailable
                    foreach ($rooms as $room) {
                        $this->initializeDaySlots($branch->id, $room->id, $date, null, null, $force);
                    }
                    continue;
                }

                $openTime = $hours['open'] ?? '06:00';
                $closeTime = $hours['close'] ?? '20:00';

                foreach ($rooms as $room) {
                    $slotsCreated = $this->initializeDaySlots(
                        $branch->id,
                        $room->id,
                        $date,
                        $openTime,
                        $closeTime,
                        $force
                    );
                    $totalSlots += $slotsCreated;
                }
            }

            $this->info("  Completed branch: {$branch->name}");
        }

        $this->info("\n✅ Grid initialization complete!");
        $this->info("Total slots created/updated: {$totalSlots}");

        return Command::SUCCESS;
    }

    /**
     * Initialize slots for a specific day and room
     */
    private function initializeDaySlots(
        int $branchId,
        int $roomId,
        Carbon $date,
        ?string $openTime,
        ?string $closeTime,
        bool $force
    ): int {
        $slotsCreated = 0;

        if ($openTime === null || $closeTime === null) {
            // Branch closed - mark all slots as unavailable
            // Create slots from 00:00 to 23:30
            for ($hour = 0; $hour < 24; $hour++) {
                for ($minute = 0; $minute < 60; $minute += 30) {
                    $timeSlot = sprintf('%02d:%02d:00', $hour, $minute);
                    
                    if ($force || !RoomAvailabilitySlot::where('room_id', $roomId)
                        ->where('date', $date->format('Y-m-d'))
                        ->where('time_slot', $timeSlot)
                        ->exists()) {
                        
                        RoomAvailabilitySlot::updateOrCreate(
                            [
                                'room_id' => $roomId,
                                'date' => $date->format('Y-m-d'),
                                'time_slot' => $timeSlot,
                            ],
                            [
                                'branch_id' => $branchId,
                                'status' => 'Unavailable',
                                'booking_id' => null,
                            ]
                        );
                        $slotsCreated++;
                    }
                }
            }
            return $slotsCreated;
        }

        // Parse times
        $openCarbon = Carbon::parse($date->format('Y-m-d') . ' ' . $openTime);
        $closeCarbon = Carbon::parse($date->format('Y-m-d') . ' ' . $closeTime);

        // Create slots in 30-minute intervals
        $current = $openCarbon->copy();
        
        while ($current->lt($closeCarbon)) {
            $timeSlot = $current->format('H:i:s');

            if ($force || !RoomAvailabilitySlot::where('room_id', $roomId)
                ->where('date', $date->format('Y-m-d'))
                ->where('time_slot', $timeSlot)
                ->exists()) {
                
                RoomAvailabilitySlot::updateOrCreate(
                    [
                        'room_id' => $roomId,
                        'date' => $date->format('Y-m-d'),
                        'time_slot' => $timeSlot,
                    ],
                    [
                        'branch_id' => $branchId,
                        'status' => 'Available',
                        'booking_id' => null,
                    ]
                );
                $slotsCreated++;
            }

            $current->addMinutes(30);
        }

        return $slotsCreated;
    }
}
