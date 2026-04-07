<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDispute;
use App\Models\TimetableSession;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule; 

class AttendanceController extends Controller
{
    /**
     * Configuration for attendance marking windows
     */
    private $earlyMarkingMinutes = 30;  // Can mark 30 mins before session
    private $lateMarkingMinutes = 15;   // Can mark 15 mins after session ends
    
    /**
     * Get today's sessions for the authenticated user
     */
    public function getTodaySessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $today = Carbon::today();
            $currentTime = Carbon::now();
            
            \Log::info('getTodaySessions called', [
                'user_id' => $user->id,
                'current_time' => $currentTime->format('Y-m-d H:i:s'),
                'today' => $today->format('Y-m-d')
            ]);
            
            // Get today's timetable sessions
            $timetableSessions = TimetableSession::where('day', $today->format('D'))
                ->orderBy('start_hour')
                ->get();
            
            \Log::info('Found sessions', ['count' => $timetableSessions->count()]);
            
            // Get existing attendance records
            $attendances = Attendance::where('user_id', $user->id)
                ->where('attendance_date', $today)
                ->get()
                ->keyBy('timetable_session_id');
            
            $sessions = [];
            foreach ($timetableSessions as $session) {
                $attendance = $attendances->get($session->id);
                
                // Calculate session times
                $sessionStartTime = Carbon::today()->setHour($session->start_hour)->setMinute(0);
                $sessionEndTime = Carbon::today()->setHour($session->start_hour + $session->duration)->setMinute(0);
                
                // Calculate marking windows
                $earlyMarkingStart = $sessionStartTime->copy()->subMinutes($this->earlyMarkingMinutes);
                $lateMarkingEnd = $sessionEndTime->copy()->addMinutes($this->lateMarkingMinutes);
                
                // Log for debugging
                \Log::info('Session ' . $session->id . ' times', [
                    'session_start' => $sessionStartTime->format('H:i'),
                    'session_end' => $sessionEndTime->format('H:i'),
                    'early_marking_start' => $earlyMarkingStart->format('H:i'),
                    'late_marking_end' => $lateMarkingEnd->format('H:i'),
                    'current_time' => $currentTime->format('H:i')
                ]);
                
                // Determine if user can mark attendance
                $canMark = false;
                $markingMessage = null;
                $isMarkingWindowOpen = false;
                $isSessionStarted = $currentTime->gte($sessionStartTime);
                $isSessionEnded = $currentTime->gt($sessionEndTime);
                $isEarlyMarking = $currentTime->between($earlyMarkingStart, $sessionStartTime);
                $isLateMarking = $currentTime->between($sessionEndTime, $lateMarkingEnd);
                
                // Check if marking window is open
                if ($currentTime->between($earlyMarkingStart, $lateMarkingEnd)) {
                    $isMarkingWindowOpen = true;
                }
                
                // If marking window is open and attendance not marked, allow marking
                if (!$attendance && $isMarkingWindowOpen) {
                    $canMark = true;
                    if ($isEarlyMarking) {
                        $markingMessage = 'Early marking allowed';
                    } elseif ($isLateMarking) {
                        $markingMessage = 'Late marking window';
                    } else {
                        $markingMessage = 'Session in progress';
                    }
                } 
                // If marking window is closed and no attendance, auto-mark as absent
                elseif (!$attendance && $currentTime->gt($lateMarkingEnd)) {
                    $attendance = $this->autoMarkAbsent($user, $session, $today);
                    $markingMessage = 'Marking window closed - Auto-marked absent';
                }
                // If attendance already exists
                elseif ($attendance) {
                    $markingMessage = 'Attendance already marked';
                }
                // If future session (before early marking starts)
                elseif ($currentTime->lt($earlyMarkingStart)) {
                    $markingMessage = 'Marking opens at ' . $earlyMarkingStart->format('h:i A');
                }
                
                $status = $attendance ? $attendance->status : 'Not Marked';
                
                $sessions[] = [
                    'id' => $session->id,
                    'session_number' => $this->getSessionNumber($session->start_hour),
                    'time_slot' => $this->formatTimeSlot($session->start_hour, $session->duration),
                    'start_time' => $sessionStartTime->format('h:i A'),
                    'end_time' => $sessionEndTime->format('h:i A'),
                    'start_hour' => $session->start_hour,
                    'end_hour' => $session->start_hour + $session->duration,
                    'course_name' => $session->subject,
                    'course_code' => $session->course ? $session->course->code : null,
                    'faculty' => $session->faculty,
                    'room' => $session->room,
                    'status' => $status,
                    'attendance_id' => $attendance ? $attendance->id : null,
                    'can_mark' => $canMark,
                    'marking_message' => $markingMessage,
                    'marked_at' => $attendance ? $attendance->marked_at : null,
                    'is_session_started' => $isSessionStarted,
                    'is_session_ended' => $isSessionEnded,
                    'is_early_marking' => $isEarlyMarking,
                    'is_late_marking' => $isLateMarking,
                    'is_marking_window_open' => $isMarkingWindowOpen,
                    'is_auto_marked' => $attendance ? ($attendance->is_auto_marked ?? false) : false,
                ];
            }
            
            $total = count($sessions);
            $present = $attendances->where('status', 'Present')->count();
            $absent = $attendances->where('status', 'Absent')->count();
            $onLeave = $attendances->where('status', 'On Leave')->count();
            $notMarked = $total - $present - $absent - $onLeave;
            $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sessions' => $sessions,
                    'current_time' => $currentTime->format('h:i A'),
                    'statistics' => [
                        'total' => $total,
                        'present' => $present,
                        'absent' => $absent,
                        'on_leave' => $onLeave,
                        'not_marked' => $notMarked,
                        'percentage' => $percentage,
                    ],
                ],
                'message' => 'Today\'s sessions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getTodaySessions error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve today\'s sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Auto mark a session as Absent when marking window has passed
     */
    private function autoMarkAbsent($user, $session, $date): ?Attendance
    {
        try {
            $trainee = Trainee::where('user_id', $user->id)->first();
            
            // Check if attendance already exists
            $existingAttendance = Attendance::where('user_id', $user->id)
                ->where('timetable_session_id', $session->id)
                ->where('attendance_date', $date)
                ->first();
            
            if ($existingAttendance && $existingAttendance->status !== 'Not Marked') {
                return $existingAttendance;
            }
            
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'trainee_id' => $trainee?->id,
                'timetable_session_id' => $session->id,
                'attendance_date' => $date,
                'status' => 'Absent',
                'marked_at' => now(),
                'remarks' => 'Auto-marked absent after marking window closed',
                'is_auto_marked' => true,
            ]);
            
            \Log::info('Auto-marked absent', [
                'user_id' => $user->id,
                'session_id' => $session->id,
                'date' => $date->format('Y-m-d'),
                'time' => now()->format('H:i:s')
            ]);
            
            return $attendance;
        } catch (\Exception $e) {
            \Log::error('Auto-mark absent failed: ' . $e->getMessage());
            return null;
        }
    }
    
    
    /**
     * Validate if a session can be marked at current time
     */
    private function validateMarkingWindow($session, $currentTime): array
    {
        $sessionStartTime = Carbon::today()->setHour($session->start_hour)->setMinute(0);
        $sessionEndTime = Carbon::today()->setHour($session->start_hour + $session->duration)->setMinute(0);
        
        $earlyMarkingStart = $sessionStartTime->copy()->subMinutes($this->earlyMarkingMinutes);
        $lateMarkingEnd = $sessionEndTime->copy()->addMinutes($this->lateMarkingMinutes);
        
        $canMark = false;
        $isLate = false;
        $message = '';
        
        if ($currentTime->lt($earlyMarkingStart)) {
            $message = 'Too early. Marking opens at ' . $earlyMarkingStart->format('h:i A');
        } elseif ($currentTime->between($earlyMarkingStart, $sessionStartTime)) {
            $canMark = true;
            $message = 'Early marking allowed';
        } elseif ($currentTime->between($sessionStartTime, $sessionEndTime)) {
            $canMark = true;
            $message = 'Session in progress';
        } elseif ($currentTime->between($sessionEndTime, $lateMarkingEnd)) {
            $canMark = true;
            $isLate = true;
            $message = 'Late marking window';
        } elseif ($currentTime->gt($lateMarkingEnd)) {
            $message = 'Marking window closed. Cannot mark attendance.';
        }
        
        return [
            'can_mark' => $canMark,
            'is_late' => $isLate,
            'message' => $message,
            'session_start_time' => $sessionStartTime,
            'session_end_time' => $sessionEndTime,
            'early_marking_start' => $earlyMarkingStart,
            'late_marking_end' => $lateMarkingEnd,
        ];
    }
    
    /**
     * Mark attendance for a session
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timetable_session_id' => 'required|exists:timetable_sessions,id',
            'status' => ['required', Rule::in(['Present', 'Absent', 'On Leave'])],
            'remarks' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        try {
            $user = $request->user();
            $session = TimetableSession::findOrFail($request->timetable_session_id);
            $today = Carbon::today();
            $currentTime = Carbon::now();
            
            // Check if user is a trainee
            if ($user->role !== 'trainee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only trainees can mark attendance'
                ], 403);
            }
            
            // Check if session is for today
            $sessionDay = $today->format('D');
            if ($session->day !== $sessionDay) {
                return response()->json([
                    'success' => false,
                    'message' => 'This session is not scheduled for today'
                ], 422);
            }
            
            // Validate marking window
            $markingValidation = $this->validateMarkingWindow($session, $currentTime);
            
            if (!$markingValidation['can_mark']) {
                return response()->json([
                    'success' => false,
                    'message' => $markingValidation['message'],
                    'data' => [
                        'session_start_time' => $markingValidation['session_start_time']->format('h:i A'),
                        'session_end_time' => $markingValidation['session_end_time']->format('h:i A'),
                        'early_marking_start' => $markingValidation['early_marking_start']->format('h:i A'),
                        'late_marking_end' => $markingValidation['late_marking_end']->format('h:i A'),
                        'current_time' => $currentTime->format('h:i A'),
                    ]
                ], 422);
            }
            
            // Check if attendance already marked
            $attendance = Attendance::where('user_id', $user->id)
                ->where('timetable_session_id', $session->id)
                ->where('attendance_date', $today)
                ->first();
            
            if ($attendance && in_array($attendance->status, ['Present', 'Absent', 'On Leave'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance already marked for this session',
                    'status' => $attendance->status,
                    'marked_at' => $attendance->marked_at
                ], 422);
            }
            
            $trainee = Trainee::where('user_id', $user->id)->first();
            
            DB::beginTransaction();
            
            $attendanceData = [
                'user_id' => $user->id,
                'trainee_id' => $trainee?->id,
                'timetable_session_id' => $session->id,
                'attendance_date' => $today,
                'status' => $request->status,
                'marked_at' => now(),
                'remarks' => $request->remarks,
                'is_auto_marked' => false,
            ];
            
            if ($attendance) {
                $attendance->update($attendanceData);
            } else {
                $attendance = Attendance::create($attendanceData);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $attendance->id,
                    'session_number' => $this->getSessionNumber($session->start_hour),
                    'status' => $attendance->status,
                    'marked_at' => $attendance->marked_at,
                    'is_late' => $markingValidation['is_late'],
                ],
                'message' => $markingValidation['is_late'] 
                    ? 'Attendance marked successfully (late marking)' 
                    : 'Attendance marked successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('markAttendance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Raise a dispute for an attendance record
     */
    public function raiseDispute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'attendance_id' => 'required|exists:attendances,id',
            'reason' => 'required|string|min:10|max:1000',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        try {
            $user = $request->user();
            $attendance = Attendance::findOrFail($request->attendance_id);
            
            if ($attendance->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to dispute this attendance record'
                ], 403);
            }
            
            // Check if dispute already exists
            $existingDispute = AttendanceDispute::where('attendance_id', $attendance->id)->first();
            if ($existingDispute) {
                return response()->json([
                    'success' => false,
                    'message' => 'A dispute has already been raised for this record',
                    'dispute_status' => $existingDispute->status
                ], 422);
            }
            
            $dispute = AttendanceDispute::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'reason' => $request->reason,
                'status' => 'Pending Review',
            ]);
            
            $attendance->is_disputed = true;
            $attendance->save();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $dispute->id,
                    'status' => $dispute->status,
                ],
                'message' => 'Dispute raised successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('raiseDispute error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to raise dispute',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get disputes for authenticated user
     */
    public function getDisputes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $disputes = AttendanceDispute::where('user_id', $user->id)
                ->with(['attendance' => function($query) {
                    $query->with('timetableSession');
                }])
                ->orderBy('created_at', 'desc')
                ->get();
            
            $disputeData = [];
            foreach ($disputes as $dispute) {
                if (!$dispute->attendance || !$dispute->attendance->timetableSession) {
                    continue;
                }
                
                $attendance = $dispute->attendance;
                $session = $attendance->timetableSession;
                
                $disputeData[] = [
                    'id' => $dispute->id,
                    'attendance_id' => $dispute->attendance_id,
                    'reason' => $dispute->reason,
                    'status' => $dispute->status,
                    'resolution_notes' => $dispute->resolution_notes,
                    'date' => $attendance->attendance_date ? $attendance->attendance_date->format('Y-m-d') : null,
                    'session_number' => $this->getSessionNumber($session->start_hour ?? 0),
                    'course_name' => $session->subject ?? 'Unknown Course',
                    'original_status' => $attendance->status ?? 'Unknown',
                    'created_at' => $dispute->created_at ? $dispute->created_at->format('Y-m-d H:i:s') : null,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $disputeData,
                'message' => 'Disputes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getDisputes error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve disputes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
 * Get attendance history for authenticated user
 */
public function getHistory(Request $request): JsonResponse
{
    try {
        $user = $request->user();
        $today = Carbon::today();
        
        // Get all attendance records except today with eager loading
        $attendances = Attendance::where('user_id', $user->id)
            ->where('attendance_date', '<', $today)
            ->with(['timetableSession' => function($query) {
                // Only select needed columns from timetable_sessions
                $query->select('id', 'start_hour', 'duration', 'subject');
            }])
            ->orderBy('attendance_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'user_id', 'timetable_session_id', 'attendance_date', 'status', 'is_auto_marked', 'created_at']);
        
        if ($attendances->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No attendance history found'
            ]);
        }
        
        // Use collection grouping for better performance
        $history = $attendances->groupBy(function($attendance) {
            return $attendance->attendance_date->format('Y-m-d');
        })->map(function($dateAttendances, $date) {
            $firstAttendance = $dateAttendances->first();
            $records = [];
            $presentCount = 0;
            
            foreach ($dateAttendances as $attendance) {
                if (!$attendance->timetableSession) {
                    continue;
                }
                
                if ($attendance->status === 'Present') {
                    $presentCount++;
                }
                
                $records[] = [
                    'id' => $attendance->id,
                    'session_number' => $this->getSessionNumber($attendance->timetableSession->start_hour),
                    'time_slot' => $this->formatTimeSlot(
                        $attendance->timetableSession->start_hour,
                        $attendance->timetableSession->duration
                    ),
                    'course_name' => $attendance->timetableSession->subject,
                    'status' => $attendance->status,
                    'is_auto_marked' => $attendance->is_auto_marked ?? false,
                ];
            }
            
            $totalCount = count($records);
            
            return [
                'date' => $date,
                'day_name' => $firstAttendance->attendance_date->format('D'),
                'present' => $presentCount,
                'total' => $totalCount,
                'percentage' => $totalCount > 0 ? round(($presentCount / $totalCount) * 100) : 0,
                'records' => $records,
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'data' => $history->toArray(),
            'message' => 'Attendance history retrieved successfully'
        ]);
    } catch (\Exception $e) {
        \Log::error('getHistory error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve attendance history',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Get attendance statistics with caching
 */
public function getStats(Request $request): JsonResponse
{
    try {
        $user = $request->user();
        
        // Use cache for frequently accessed statistics (cache for 5 minutes)
        $cacheKey = 'attendance_stats_user_' . $user->id;
        
        $stats = cache()->remember($cacheKey, 300, function() use ($user) {
            // Use aggregate queries instead of multiple count queries
            $totals = Attendance::where('user_id', $user->id)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'On Leave' THEN 1 ELSE 0 END) as on_leave
                ")
                ->first();
            
            $monthStart = Carbon::now()->startOfMonth();
            $monthTotals = Attendance::where('user_id', $user->id)
                ->where('attendance_date', '>=', $monthStart)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
                ")
                ->first();
            
            $total = $totals->total ?? 0;
            $present = $totals->present ?? 0;
            
            return [
                'total' => $total,
                'present' => $present,
                'absent' => $totals->absent ?? 0,
                'on_leave' => $totals->on_leave ?? 0,
                'percentage' => $total > 0 ? round(($present / $total) * 100) : 0,
                'monthly' => [
                    'total' => $monthTotals->total ?? 0,
                    'present' => $monthTotals->present ?? 0,
                    'percentage' => ($monthTotals->total ?? 0) > 0 
                        ? round((($monthTotals->present ?? 0) / ($monthTotals->total ?? 0)) * 100) 
                        : 0,
                ],
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Attendance statistics retrieved successfully'
        ]);
    } catch (\Exception $e) {
        \Log::error('getStats error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve attendance statistics',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get attendance history for authenticated user
     */
    // public function getHistory(Request $request): JsonResponse
    // {
    //     try {
    //         $user = $request->user();
    //         $today = Carbon::today();
    //         $historyArray = [];
            
    //         // Get all attendance records except today
    //         $attendances = Attendance::where('user_id', $user->id)
    //             ->where('attendance_date', '<', $today)
    //             ->with('timetableSession')
    //             ->orderBy('attendance_date', 'desc')
    //             ->orderBy('created_at', 'desc')
    //             ->get();
            
    //         if ($attendances->isEmpty()) {
    //             return response()->json([
    //                 'success' => true,
    //                 'data' => [],
    //                 'message' => 'No attendance history found'
    //             ]);
    //         }
            
    //         // Group by date
    //         $history = [];
    //         foreach ($attendances as $attendance) {
    //             if (!$attendance->timetableSession) {
    //                 continue;
    //             }
                
    //             $date = $attendance->attendance_date->format('Y-m-d');
    //             if (!isset($history[$date])) {
    //                 $history[$date] = [
    //                     'date' => $date,
    //                     'day_name' => $attendance->attendance_date->format('D'),
    //                     'records' => [],
    //                     'present_count' => 0,
    //                     'total_count' => 0,
    //                 ];
    //             }
                
    //             $history[$date]['records'][] = [
    //                 'id' => $attendance->id,
    //                 'session_number' => $this->getSessionNumber($attendance->timetableSession->start_hour),
    //                 'time_slot' => $this->formatTimeSlot(
    //                     $attendance->timetableSession->start_hour,
    //                     $attendance->timetableSession->duration
    //                 ),
    //                 'course_name' => $attendance->timetableSession->subject,
    //                 'status' => $attendance->status,
    //                 'is_auto_marked' => $attendance->is_auto_marked ?? false,
    //             ];
                
    //             $history[$date]['total_count']++;
    //             if ($attendance->status === 'Present') {
    //                 $history[$date]['present_count']++;
    //             }
    //         }
            
    //         // Convert to array and calculate percentages
    //         foreach ($history as $date => $data) {
    //             $historyArray[] = [
    //                 'date' => $data['date'],
    //                 'day_name' => $data['day_name'],
    //                 'present' => $data['present_count'],
    //                 'total' => $data['total_count'],
    //                 'percentage' => $data['total_count'] > 0 
    //                     ? round(($data['present_count'] / $data['total_count']) * 100) 
    //                     : 0,
    //                 'records' => $data['records'],
    //             ];
    //         }
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => $historyArray,
    //             'message' => 'Attendance history retrieved successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('getHistory error: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve attendance history',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
    // /**
    //  * Get attendance statistics
    //  */
    // public function getStats(Request $request): JsonResponse
    // {
    //     try {
    //         $user = $request->user();
            
    //         $total = Attendance::where('user_id', $user->id)->count();
    //         $present = Attendance::where('user_id', $user->id)
    //             ->where('status', 'Present')
    //             ->count();
    //         $absent = Attendance::where('user_id', $user->id)
    //             ->where('status', 'Absent')
    //             ->count();
    //         $onLeave = Attendance::where('user_id', $user->id)
    //             ->where('status', 'On Leave')
    //             ->count();
            
    //         $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
            
    //         $monthStart = Carbon::now()->startOfMonth();
    //         $monthTotal = Attendance::where('user_id', $user->id)
    //             ->where('attendance_date', '>=', $monthStart)
    //             ->count();
    //         $monthPresent = Attendance::where('user_id', $user->id)
    //             ->where('attendance_date', '>=', $monthStart)
    //             ->where('status', 'Present')
    //             ->count();
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'total' => $total,
    //                 'present' => $present,
    //                 'absent' => $absent,
    //                 'on_leave' => $onLeave,
    //                 'percentage' => $percentage,
    //                 'monthly' => [
    //                     'total' => $monthTotal,
    //                     'present' => $monthPresent,
    //                     'percentage' => $monthTotal > 0 ? round(($monthPresent / $monthTotal) * 100) : 0,
    //                 ],
    //             ],
    //             'message' => 'Attendance statistics retrieved successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('getStats error: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve attendance statistics',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
    /**
     * Helper method to get session number based on start hour
     */
    private function getSessionNumber($startHour): int
    {
        $mapping = [
            8 => 1,
            9 => 2,
            10 => 3,
            11 => 4,
            12 => 5,
            13 => 6,
            14 => 7,
        ];
        return $mapping[$startHour] ?? 1;
    }
    
    /**
     * Helper method to format time slot
     */
    private function formatTimeSlot($startHour, $duration): string
    {
        $endHour = $startHour + $duration;
        $startFormat = $startHour > 12 ? ($startHour - 12) . ':00 PM' : $startHour . ':00 AM';
        $endFormat = $endHour > 12 ? ($endHour - 12) . ':00 PM' : $endHour . ':00 AM';
        return "{$startFormat} - {$endFormat}";
    }
}