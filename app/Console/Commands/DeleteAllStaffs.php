<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\TherapySession;
use App\Models\StaffTreatmentAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DeleteAllStaffs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:delete-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all staffs and their bookings (therapy sessions). Session types are preserved.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Deleting all staffs and their bookings...');

        try {
            DB::beginTransaction();

            // Count records before deletion
            $staffCount = Staff::count();
            $bookingCount = TherapySession::count();
            $assignmentCount = StaffTreatmentAssignment::count();

            $this->info("Found {$staffCount} staffs, {$bookingCount} bookings, and {$assignmentCount} staff-treatment assignments to delete.");

            // Delete all therapy sessions (bookings) first
            if ($bookingCount > 0) {
                TherapySession::query()->delete();
                $this->info("✓ Deleted {$bookingCount} therapy sessions (bookings).");
            }

            // Delete all staff-treatment assignments
            if ($assignmentCount > 0) {
                StaffTreatmentAssignment::query()->delete();
                $this->info("✓ Deleted {$assignmentCount} staff-treatment assignments.");
            }

            // Delete all staffs
            if ($staffCount > 0) {
                Staff::query()->delete();
                $this->info("✓ Deleted {$staffCount} staffs.");
            }

            DB::commit();

            $this->info('');
            $this->info('✓ Successfully deleted all staffs and their bookings.');
            $this->info('✓ Session types have been preserved.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to delete staffs: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
