<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'abhishekverman3459@gmail.com';

        $user = User::where('email', $email)->first();

        if ($user === null) {
            User::create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make('password123'), // Change this password in production
                'role' => 'super_admin',
                'branch_id' => null,
            ]);

            $this->command->info('Super admin user created successfully.');
        } else {
            $this->command->info('Super admin user already exists.');
        }
    }
}
