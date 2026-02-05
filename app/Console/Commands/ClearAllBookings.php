<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TherapySession;
use App\Models\RoomAvailabilitySlot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ClearAllBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all therapy session bookings and reset room availability slots';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing all bookings...');

        try {
            DB::beginTransaction();

            // Count bookings before deletion
            $bookingCount = TherapySession::count();
            $this->info("Found {$bookingCount} bookings to delete.");

            // Delete all therapy sessions
            TherapySession::query()->delete();
            $this->info('✓ All therapy sessions deleted.');

            // Reset room availability slots (set booked slots back to available)
            $slotCount = RoomAvailabilitySlot::where('status', 'Booked')
                ->orWhereNotNull('booking_id')
                ->count();
            
            if ($slotCount > 0) {
                RoomAvailabilitySlot::where('status', 'Booked')
                    ->orWhereNotNull('booking_id')
                    ->update([
                        'status' => 'Available',
                        'booking_id' => null,
                    ]);
                $this->info("✓ Reset {$slotCount} room availability slots to Available.");
            }

            DB::commit();

            $this->info('');
            $this->info('✓ Successfully cleared all bookings and reset room availability slots.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to clear bookings: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
