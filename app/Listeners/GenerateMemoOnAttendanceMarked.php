<?php
// app/Listeners/GenerateMemoOnAttendanceMarked.php

namespace App\Listeners;

use App\Events\AttendanceMarked;
use App\Services\MemoGenerationService;
use Illuminate\Support\Facades\Log;

class GenerateMemoOnAttendanceMarked
{
    protected $memoService;
    
    public function __construct(MemoGenerationService $memoService)
    {
        $this->memoService = $memoService;
    }
    
    public function handle(AttendanceMarked $event)
    {
        $attendance = $event->attendance;
        
        // Only process if marked as absent
        if ($attendance->status === 'absent') {
            Log::info("Attendance marked as absent for trainee {$attendance->trainee_id} on {$attendance->date}");
            
            // Check and generate memo
            $result = $this->memoService->checkAndGenerateMemo(
                $attendance->trainee_id,
                $attendance->date
            );
            
            if ($result['generated']) {
                Log::info("Memo generated automatically via event: {$result['memo']->memo_number}");
            }
        }
    }
}