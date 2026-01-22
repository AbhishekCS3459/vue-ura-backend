<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

final class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'vecura-indranagar',
                'city' => 'Bangalore',
                'opening_hours' => [
                    'Monday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '17:00'],
                    'Tuesday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '17:00'],
                    'Wednesday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '17:00'],
                    'Thursday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '17:00'],
                    'Friday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '17:00'],
                    'Saturday' => ['isOpen' => true, 'startTime' => '10:00', 'endTime' => '15:00'],
                    'Sunday' => ['isOpen' => false, 'startTime' => '10:00', 'endTime' => '15:00'],
                ],
            ],
            [
                'name' => 'vecura-koramangala',
                'city' => 'Bangalore',
                'opening_hours' => [
                    'Monday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '18:00'],
                    'Tuesday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '18:00'],
                    'Wednesday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '18:00'],
                    'Thursday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '18:00'],
                    'Friday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '18:00'],
                    'Saturday' => ['isOpen' => true, 'startTime' => '10:00', 'endTime' => '16:00'],
                    'Sunday' => ['isOpen' => false, 'startTime' => '10:00', 'endTime' => '15:00'],
                ],
            ],
            [
                'name' => 'vecura-whitefield',
                'city' => 'Bangalore',
                'opening_hours' => [
                    'Monday' => ['isOpen' => true, 'startTime' => '08:00', 'endTime' => '17:00'],
                    'Tuesday' => ['isOpen' => true, 'startTime' => '08:00', 'endTime' => '17:00'],
                    'Wednesday' => ['isOpen' => true, 'startTime' => '08:00', 'endTime' => '17:00'],
                    'Thursday' => ['isOpen' => true, 'startTime' => '08:00', 'endTime' => '17:00'],
                    'Friday' => ['isOpen' => true, 'startTime' => '08:00', 'endTime' => '17:00'],
                    'Saturday' => ['isOpen' => true, 'startTime' => '09:00', 'endTime' => '15:00'],
                    'Sunday' => ['isOpen' => false, 'startTime' => '09:00', 'endTime' => '15:00'],
                ],
            ],
        ];

        foreach ($branches as $branchData) {
            Branch::firstOrCreate(
                ['name' => $branchData['name']],
                [
                    'city' => $branchData['city'],
                    'opening_hours' => $branchData['opening_hours'],
                ]
            );
        }

        $this->command->info('Branches seeded successfully.');
    }
}
