<?php
// database/migrations/2024_03_25_000004_create_missing_trainee_profiles.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get all trainee users without profiles
        $traineesWithoutProfiles = DB::table('users')
            ->where('role', 'trainee')
            ->whereNotIn('id', function($query) {
                $query->select('user_id')->from('trainees');
            })
            ->get();
        
        // Create profiles for them
        foreach ($traineesWithoutProfiles as $trainee) {
            DB::table('trainees')->insert([
                'user_id' => $trainee->id,
                'roll_number' => 'TMP-' . $trainee->id,
                'service' => 'IFS',
                'group' => 'Alpha',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    public function down(): void
    {
        // Remove temporary profiles
        DB::table('trainees')
            ->where('roll_number', 'LIKE', 'TMP-%')
            ->delete();
    }
};