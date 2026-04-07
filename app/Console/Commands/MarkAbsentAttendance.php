<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TimetableSession;
use App\Models\Attendance;
use App\Models\Trainee;
use App\Models\User;
use Carbon\Carbon;

class MarkAbsentAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-absent';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-mark absent for sessions that have passed the marking window';
    
    /**
     * Late marking window in minutes after session ends
     */
    private $lateMarkingWindowMinutes = 15;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $currentTime = Carbon::now();
        
        $this->info("Running auto-mark absent at {$currentTime->format('Y-m-d H:i:s')}");
        
        // Get all trainee users
        $users = User::where('role', 'trainee')->get();
        
        if ($users->isEmpty()) {
            $this->info("No trainee users found.");
            return 0;
        }
        
        $totalMarked = 0;
        
        foreach ($users as $user) {
            // Get today's sessions
            $sessions = TimetableSession::where('day', $today->format('D'))->get();
            
            foreach ($sessions as $session) {
                $sessionEndTime = Carbon::today()->setHour($session->start_hour + $session->duration)->setMinute(0);
                $lateMarkingEnd = $sessionEndTime->copy()->addMinutes($this->lateMarkingWindowMinutes);
                
                // Check if marking window has passed
                if ($currentTime->gt($lateMarkingEnd)) {
                    // Check if attendance exists
                    $attendance = Attendance::where('user_id', $user->id)
                        ->where('timetable_session_id', $session->id)
                        ->where('attendance_date', $today)
                        ->first();
                    
                    // If no attendance or still 'Not Marked', mark as absent
                    if (!$attendance || $attendance->status === 'Not Marked') {
                        $trainee = Trainee::where('user_id', $user->id)->first();
                        
                        if ($attendance) {
                            $attendance->update([
                                'status' => 'Absent',
                                'marked_at' => $currentTime,
                                'remarks' => 'Auto-marked absent after marking window closed',
                                'is_auto_marked' => true,
                            ]);
                        } else {
                            Attendance::create([
                                'user_id' => $user->id,
                                'trainee_id' => $trainee?->id,
                                'timetable_session_id' => $session->id,
                                'attendance_date' => $today,
                                'status' => 'Absent',
                                'marked_at' => $currentTime,
                                'remarks' => 'Auto-marked absent after marking window closed',
                                'is_auto_marked' => true,
                            ]);
                        }
                        
                        $totalMarked++;
                        $this->info("Marked absent for user {$user->id}, session {$session->id}");
                    }
                }
            }
        }
        
        $this->info("Auto-marking completed. Marked {$totalMarked} absent records.");
        
        return 0;
    }
}