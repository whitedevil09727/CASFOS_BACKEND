<?php
// database/seeders/LeaveBalanceSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\LeaveBalance;
use Carbon\Carbon;

class LeaveBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'trainee')->get();
        $year = Carbon::now()->year;
        
        foreach ($users as $user) {
            LeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                [
                    'total_days' => 12,
                    'used_days' => 0,
                    'pending_days' => 0,
                ]
            );
        }
        
        $this->command->info('Leave balances created for all trainees');
    }
}