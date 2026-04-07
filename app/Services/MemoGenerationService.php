<?php
// app/Services/MemoGenerationService.php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Memo;
use App\Models\TimetableSession;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MemoGenerationService
{
    /**
     * Generate memos for a specific date
     */
    public function generateMemosForDate($date)
    {
        try {
            DB::beginTransaction();
            
            // Get all timetable sessions for reference
            $timetableSessions = TimetableSession::all();
            
            // Get all absent attendance records for the date with relationships
            $absentAttendances = Attendance::with(['trainee', 'timetableSession'])
                ->where('attendance_date', $date)
                ->where('status', 'Absent')
                ->get();
            
            if ($absentAttendances->isEmpty()) {
                return [
                    'success' => true,
                    'generated_count' => 0,
                    'skipped_count' => 0,
                    'message' => 'No absent records found for this date'
                ];
            }
            
            // Group absent records by trainee
            $groupedByTrainee = $absentAttendances->groupBy('trainee_id');
            $generatedCount = 0;
            $generatedMemos = [];
            $skippedCount = 0;
            
            foreach ($groupedByTrainee as $traineeId => $attendances) {
                // Get the trainee from the Trainee model
                $trainee = Trainee::find($traineeId);
                
                if (!$trainee) {
                    Log::warning("Trainee not found for ID: {$traineeId}");
                    $skippedCount++;
                    continue;
                }
                
                // Check if trainee has approved leave covering this date
                $hasApprovedLeave = LeaveRequest::where('trainee_id', $traineeId)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->exists();
                
                if ($hasApprovedLeave) {
                    $skippedCount++;
                    Log::info("Skipping memo for trainee {$trainee->name} on {$date} - has approved leave");
                    continue;
                }
                
                // Check if memo already exists for this trainee and date
                $existingMemo = Memo::where('trainee_id', $traineeId)
                    ->where('date', $date)
                    ->exists();
                
                if ($existingMemo) {
                    $skippedCount++;
                    Log::info("Memo already exists for trainee {$trainee->name} on {$date}");
                    continue;
                }
                
                // Get all absent sessions for this trainee with their details
                $absentSessionsData = [];
                foreach ($attendances as $attendance) {
                    $session = $attendance->timetableSession;
                    if ($session) {
                        $absentSessionsData[] = [
                            'session_id' => $session->id,
                            'sessionNumber' => $this->getSessionNumber($session->start_hour, $session->id),
                            'timeSlot' => $session->time_range,
                            'courseName' => $session->subject,
                            'topic' => $session->topic,
                            'faculty' => $session->faculty,
                            'room' => $session->room
                        ];
                    }
                }
                
                // Sort sessions by time
                usort($absentSessionsData, function ($a, $b) {
                    return $a['sessionNumber'] - $b['sessionNumber'];
                });
                
                // Generate memo number
                $memoNumber = $this->generateMemoNumber();
                
                // Get batch name from trainee's batch relationship
                $batchName = $trainee->batch->name ?? 'Foundation Course';
                
                // Get roll number from trainee (field is roll_number)
                $rollNumber = $trainee->roll_number ?? null;
                if (empty($rollNumber)) {
                    $rollNumber = 'N/A-' . $traineeId;
                    Log::info("Trainee {$trainee->name} has no roll number, using {$rollNumber}");
                }
                
                // Create the memo
                $memo = Memo::create([
                    'memo_number' => $memoNumber,
                    'trainee_id' => $traineeId,
                    'trainee_name' => $trainee->name ?? 'Unknown',
                    'trainee_roll_no' => $rollNumber, // Storing roll_number in the memo table
                    'batch_name' => $batchName,
                    'course_name' => 'Foundation Course', // You can adjust this if needed
                    'date' => $date,
                    'absent_sessions' => $absentSessionsData,
                    'status' => 'pending_approval',
                    'generated_at' => now()
                ]);
                
                $generatedCount++;
                $generatedMemos[] = $memo;
                
                Log::info("Generated memo {$memoNumber} for trainee {$trainee->name} (Roll: {$rollNumber}) on {$date}");
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'generated_count' => $generatedCount,
                'skipped_count' => $skippedCount,
                'generated_memos' => $generatedMemos,
                'message' => "Successfully generated {$generatedCount} memo(s)" . ($skippedCount > 0 ? ", skipped {$skippedCount}" : "")
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error generating memos for date {$date}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return [
                'success' => false,
                'generated_count' => 0,
                'skipped_count' => 0,
                'message' => "Failed to generate memos: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate memos for a date range
     */
    public function generateMemosForDateRange($startDate, $endDate)
    {
        $dates = Carbon::parse($startDate)->daysUntil(Carbon::parse($endDate));
        $results = [];
        $totalGenerated = 0;
        $totalSkipped = 0;
        
        foreach ($dates as $date) {
            $result = $this->generateMemosForDate($date->format('Y-m-d'));
            $results[$date->format('Y-m-d')] = $result;
            $totalGenerated += $result['generated_count'] ?? 0;
            $totalSkipped += $result['skipped_count'] ?? 0;
        }
        
        return [
            'success' => true,
            'total_generated' => $totalGenerated,
            'total_skipped' => $totalSkipped,
            'details' => $results,
            'message' => "Generated {$totalGenerated} memo(s), skipped {$totalSkipped} across the date range"
        ];
    }
    
    /**
     * Get all trainees who were absent on a specific date without approved leave
     */
    public function getAbsentTraineesWithoutLeave($date)
    {
        $absentAttendances = Attendance::with(['trainee', 'timetableSession'])
            ->where('attendance_date', $date)
            ->where('status', 'Absent')
            ->get();
        
        $traineesWithAbsences = $absentAttendances->groupBy('trainee_id');
        $result = [];
        
        foreach ($traineesWithAbsences as $traineeId => $attendances) {
            $trainee = Trainee::find($traineeId);
            
            if (!$trainee) {
                continue;
            }
            
            $hasApprovedLeave = LeaveRequest::where('trainee_id', $traineeId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->exists();
            
            if (!$hasApprovedLeave) {
                $memoExists = Memo::where('trainee_id', $traineeId)
                    ->where('date', $date)
                    ->exists();
                
                $absentSessions = [];
                foreach ($attendances as $attendance) {
                    $session = $attendance->timetableSession;
                    if ($session) {
                        $absentSessions[] = [
                            'session_number' => $this->getSessionNumber($session->start_hour, $session->id),
                            'time_slot' => $session->time_range,
                            'subject' => $session->subject,
                            'topic' => $session->topic
                        ];
                    }
                }
                
                $result[] = [
                    'trainee' => $trainee,
                    'trainee_id' => $traineeId,
                    'trainee_roll_number' => $trainee->roll_number ?? 'N/A',
                    'absent_sessions_count' => count($absentSessions),
                    'absent_sessions' => $absentSessions,
                    'has_memo' => $memoExists
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Check and generate memo for a specific trainee and date
     */
    public function checkAndGenerateMemo($traineeId, $date)
    {
        try {
            DB::beginTransaction();
            
            $trainee = Trainee::find($traineeId);
            if (!$trainee) {
                return ['generated' => false, 'reason' => 'Trainee not found'];
            }
            
            $hasApprovedLeave = LeaveRequest::where('trainee_id', $traineeId)
                ->where('status', 'approved')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->exists();
            
            if ($hasApprovedLeave) {
                return ['generated' => false, 'reason' => 'Trainee has approved leave for this date'];
            }
            
            $existingMemo = Memo::where('trainee_id', $traineeId)
                ->where('date', $date)
                ->first();
            
            if ($existingMemo) {
                return ['generated' => false, 'reason' => 'Memo already exists for this date'];
            }
            
            $absentAttendances = Attendance::with(['timetableSession'])
                ->where('trainee_id', $traineeId)
                ->where('attendance_date', $date)
                ->where('status', 'Absent')
                ->get();
            
            if ($absentAttendances->isEmpty()) {
                return ['generated' => false, 'reason' => 'No absent sessions found for this date'];
            }
            
            $absentSessionsData = [];
            foreach ($absentAttendances as $attendance) {
                $session = $attendance->timetableSession;
                if ($session) {
                    $absentSessionsData[] = [
                        'session_id' => $session->id,
                        'sessionNumber' => $this->getSessionNumber($session->start_hour, $session->id),
                        'timeSlot' => $session->time_range,
                        'courseName' => $session->subject,
                        'topic' => $session->topic,
                        'faculty' => $session->faculty,
                        'room' => $session->room
                    ];
                }
            }
            
            usort($absentSessionsData, function ($a, $b) {
                return $a['sessionNumber'] - $b['sessionNumber'];
            });
            
            $memoNumber = $this->generateMemoNumber();
            
            // Get roll number from trainee (field is roll_number)
            $rollNumber = $trainee->roll_number ?? 'N/A-' . $traineeId;
            
            $memo = Memo::create([
                'memo_number' => $memoNumber,
                'trainee_id' => $traineeId,
                'trainee_name' => $trainee->name ?? 'Unknown',
                'trainee_roll_no' => $rollNumber,
                'batch_name' => $trainee->batch->name ?? 'Foundation Course',
                'course_name' => 'Foundation Course',
                'date' => $date,
                'absent_sessions' => $absentSessionsData,
                'status' => 'pending_approval',
                'generated_at' => now()
            ]);
            
            DB::commit();
            
            Log::info("Generated memo {$memoNumber} for trainee {$trainee->name} on {$date}");
            
            return [
                'generated' => true,
                'memo' => $memo,
                'message' => 'Memo generated successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error checking/generating memo: " . $e->getMessage());
            return ['generated' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate unique memo number
     */
    private function generateMemoNumber()
    {
        $year = Carbon::now()->format('Y');
        $prefix = "MEMO-{$year}-";
        
        $lastMemo = Memo::where('memo_number', 'like', "MEMO-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastMemo) {
            $lastNumber = intval(substr($lastMemo->memo_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . $newNumber;
    }
    
    /**
     * Get session number based on start hour
     */
    private function getSessionNumber($startHour, $sessionId = null)
    {
        // Extended session mapping for all possible hours
        $sessionMap = [
            9 => 1,   // 9:00 AM
            10 => 2,  // 10:00 AM
            11 => 3,  // 11:00 AM
            12 => 4,  // 12:00 PM
            13 => 5,  // 1:00 PM
            14 => 6,  // 2:00 PM
            15 => 7,  // 3:00 PM
            16 => 8,  // 4:00 PM
        ];
        
        if (isset($sessionMap[$startHour])) {
            return $sessionMap[$startHour];
        }
        
        // Fallback: use session id modulo 10 or something
        return $sessionId ? ($sessionId % 10) : $startHour;
    }
}