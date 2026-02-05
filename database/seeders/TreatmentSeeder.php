<?php

namespace Database\Seeders;

use App\Models\Treatment;
use Illuminate\Database\Seeder;

class TreatmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $treatments = [
            'Cryotherapy',
            'Body Massage',
            'Diet Consultation',
            'Laser',
            'Cupping Therapy',
            'Wellness Assessment',
        ];

        foreach ($treatments as $treatmentName) {
            Treatment::firstOrCreate(
                ['name' => $treatmentName]
            );
        }
    }
}
