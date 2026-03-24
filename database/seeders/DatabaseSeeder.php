<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        DB::table('users')->truncate();
        
        // Create users with only the columns we know exist from your structure
        $users = [
            [
                'username' => 'admin',
                'name' => 'System Administrator',
                'email' => 'admin@casfos.gov.in',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'course_director',
                'name' => 'Dr. Rajesh Kumar',
                'email' => 'director@casfos.gov.in',
                'password' => Hash::make('password'),
                'role' => 'course_director',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'dr_sharma',
                'name' => 'Dr. Anil Sharma',
                'email' => 'faculty1@casfos.gov.in',
                'password' => Hash::make('password'),
                'role' => 'faculty',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'prof_verma',
                'name' => 'Prof. Sunita Verma',
                'email' => 'faculty2@casfos.gov.in',
                'password' => Hash::make('password'),
                'role' => 'faculty',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'dr_patel',
                'name' => 'Dr. Amit Patel',
                'email' => 'faculty3@casfos.gov.in',
                'password' => Hash::make('password'),
                'role' => 'faculty',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'trainee_singh',
                'name' => 'Raj Singh',
                'email' => 'trainee1@example.com',
                'password' => Hash::make('password'),
                'role' => 'trainee',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'trainee_kumar',
                'name' => 'Amit Kumar',
                'email' => 'trainee2@example.com',
                'password' => Hash::make('password'),
                'role' => 'trainee',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'trainee_sharma',
                'name' => 'Priya Sharma',
                'email' => 'trainee3@example.com',
                'password' => Hash::make('password'),
                'role' => 'trainee',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'trainee_patel',
                'name' => 'Neha Patel',
                'email' => 'trainee4@example.com',
                'password' => Hash::make('password'),
                'role' => 'trainee',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'trainee_reddy',
                'name' => 'Vikram Reddy',
                'email' => 'trainee5@example.com',
                'password' => Hash::make('password'),
                'role' => 'trainee',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        foreach ($users as $user) {
            try {
                $id = DB::table('users')->insertGetId($user);
                $this->command->info("✓ Created user: {$user['email']} (ID: {$id})");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to create {$user['email']}: " . $e->getMessage());
            }
        }
        
        // Call CourseSeeder to seed courses
        $this->call(CourseSeeder::class);
        
        $this->command->info('Database seeding completed!');
    }
}