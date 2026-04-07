<?php
// database/seeders/AttendanceSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\TimetableSession;
use App\Models\User;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run()
    {
        $trainee = User::where('role', 'trainee')->first();
        $sessions = TimetableSession::take(10)->get();
        
        foreach ($sessions as $session) {
            Attendance::create([
                'user_id' => $trainee->id,
                'timetable_session_id' => $session->id,
                'attendance_date' => Carbon::today()->subDays(rand(1, 10)),
                'status' => rand(0, 1) ? 'Present' : 'Absent',
                'marked_at' => now(),
            ]);
        }
    }
}