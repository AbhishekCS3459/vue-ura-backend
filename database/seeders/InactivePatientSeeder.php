<?php

namespace Database\Seeders;

use App\Models\InactivePatient;
use App\Models\Patient;
use App\Models\Branch;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class InactivePatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $branches = Branch::all();
        $patients = Patient::all();
        $staff = Staff::all();

        if ($branches->isEmpty() || $patients->isEmpty() || $staff->isEmpty()) {
            $this->command->warn('Please seed branches, patients, and staff first before seeding inactive patients.');
            return;
        }

        // Create sample inactive patients
        $statuses = ['Follow-up', 'Did not reply', 'Did not pick up', 'Next', 'Ask for callback'];
        $actions = ['None', 'Message sent', 'Follow up call made'];

        // Create 10-15 inactive patients
        $count = 12;
        for ($i = 0; $i < $count; $i++) {
            $branch = $branches->random();
            $patient = $patients->random();
            $therapist = $staff->random();

            // Random days since last session (50-120 days)
            $daysSince = rand(50, 120);
            $lastSessionDate = Carbon::now()->subDays($daysSince);

            // Random status and action
            $status = $statuses[array_rand($statuses)];
            $action = $actions[array_rand($actions)];

            // Random next follow-up date (some have it, some don't)
            $nextFollowUpDate = rand(0, 1) ? Carbon::now()->addDays(rand(1, 14))->format('Y-m-d') : null;

            // Check if this patient-branch combination already exists
            $exists = InactivePatient::where('patient_id', $patient->id)
                ->where('branch_id', $branch->id)
                ->exists();

            if (!$exists) {
                InactivePatient::create([
                    'branch_id' => $branch->id,
                    'patient_id' => $patient->id,
                    'name' => $patient->name,
                    'phone' => $patient->phone ?? '0000000000',
                    'last_session_date' => $lastSessionDate->format('Y-m-d'),
                    'days_since_last_session' => $daysSince,
                    'last_therapist' => $therapist->name,
                    'last_therapist_id' => $therapist->id,
                    'status' => $status,
                    'last_action' => $action,
                    'last_status_update' => Carbon::now()->subDays(rand(0, 30)),
                    'next_follow_up_date' => $nextFollowUpDate,
                ]);
            }
        }

        $this->command->info("Seeded inactive patients successfully!");
    }
}
