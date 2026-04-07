<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Trainee;

class TraineeProfileSeeder extends Seeder
{
    public function run()
    {
        $users = User::where('role', 'trainee')->get();
        
        foreach ($users as $user) {
            Trainee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'roll_number' => 'TR-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                    'name' => $user->name,
                    'email' => $user->email,
                    'service_type' => 'IFS',
                    'enrollment_status' => 'Enrolled',
                    'gender' => 'Male',
                ]
            );
        }
        
        $this->command->info('Trainee profiles created for all trainee users');
    }
}