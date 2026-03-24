<?php
// database/seeders/FacultySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FacultySeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user only if it doesn't exist
        if (!User::where('email', 'admin@casfos.gov.in')->exists()) {
            User::create([
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@casfos.gov.in',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]);
            $this->command->info('Admin user created successfully.');
        } else {
            $this->command->info('Admin user already exists. Skipping...');
        }

        // Create faculty members only if they don't exist
        $faculty = [
            ['Dr. Rajesh Kumar', 'rajesh.kumar', 'rajesh@casfos.gov.in'],
            ['Dr. Priya Sharma', 'priya.sharma', 'priya@casfos.gov.in'],
            ['Prof. Venkatesh Rao', 'venkatesh.rao', 'venkatesh@casfos.gov.in'],
            ['Dr. Anita Menon', 'anita.menon', 'anita@casfos.gov.in'],
            ['Prof. Suresh Nair', 'suresh.nair', 'suresh@casfos.gov.in'],
        ];

        foreach ($faculty as $f) {
            if (!User::where('email', $f[2])->exists()) {
                User::create([
                    'name' => $f[0],
                    'username' => $f[1],
                    'email' => $f[2],
                    'password' => Hash::make('password123'),
                    'role' => 'faculty',
                ]);
                $this->command->info("Faculty {$f[0]} created successfully.");
            } else {
                $this->command->info("Faculty {$f[0]} already exists. Skipping...");
            }
        }

        // Create trainees only if they don't exist
        $trainees = [
            ['Arjun Mehta', 'arjun.mehta', 'arjun@trainee.casfos.gov.in'],
            ['Kavitha Rao', 'kavitha.rao', 'kavitha@trainee.casfos.gov.in'],
            ['Raman Pillai', 'raman.pillai', 'raman@trainee.casfos.gov.in'],
        ];

        foreach ($trainees as $t) {
            if (!User::where('email', $t[2])->exists()) {
                User::create([
                    'name' => $t[0],
                    'username' => $t[1],
                    'email' => $t[2],
                    'password' => Hash::make('password123'),
                    'role' => 'trainee',
                ]);
                $this->command->info("Trainee {$t[0]} created successfully.");
            } else {
                $this->command->info("Trainee {$t[0]} already exists. Skipping...");
            }
        }

        $this->command->info('Seeding completed!');
    }
}