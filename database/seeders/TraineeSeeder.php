<?php
// database/seeders/TraineeSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trainee;

class TraineeSeeder extends Seeder
{
    public function run(): void
    {
        $trainees = [
            ['roll_number' => 'R26-001', 'name' => 'Rahul Sharma', 'gender' => 'Male', 'service_type' => 'IFS', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-002', 'name' => 'Priya Desai', 'gender' => 'Female', 'service_type' => 'IFS', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-003', 'name' => 'Amit Kumar', 'gender' => 'Male', 'service_type' => 'IFS', 'enrollment_status' => 'Pending'],
            ['roll_number' => 'R26-004', 'name' => 'Sneha Reddy', 'gender' => 'Female', 'service_type' => 'SFS', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-005', 'name' => 'Vikram Singh', 'gender' => 'Male', 'service_type' => 'SFS', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-006', 'name' => 'Anjali Gupta', 'gender' => 'Female', 'service_type' => 'SFS', 'enrollment_status' => 'Withdrawn'],
            ['roll_number' => 'R26-007', 'name' => 'Rohan Verma', 'gender' => 'Male', 'service_type' => 'SFS', 'enrollment_status' => 'Pending'],
            ['roll_number' => 'R26-008', 'name' => 'Kavita R', 'gender' => 'Female', 'service_type' => 'Other', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-009', 'name' => 'Arjun Nair', 'gender' => 'Male', 'service_type' => 'Other', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-010', 'name' => 'Meera Patel', 'gender' => 'Female', 'service_type' => 'IFS', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-011', 'name' => 'Suresh Pillai', 'gender' => 'Male', 'service_type' => 'IFS', 'enrollment_status' => 'Enrolled'],
            ['roll_number' => 'R26-012', 'name' => 'Neha Joshi', 'gender' => 'Female', 'service_type' => 'SFS', 'enrollment_status' => 'Pending'],
        ];
        
        foreach ($trainees as $trainee) {
            Trainee::create($trainee);
        }
    }
}