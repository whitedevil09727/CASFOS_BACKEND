<?php
// app/Http/Controllers/Api/AttendanceMonitoringController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Trainee;
use App\Models\Batch;
use App\Models\TimetableSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AttendanceMonitoringController extends Controller
{
    /**
     * Get all batches with trainee counts
     */
    public function getBatches(): JsonResponse
    {
        try {
            $batches = Batch::orderBy('name')->get();
            
            $batchData = $batches->map(function ($batch) {
                $traineeCount = Trainee::where('batch_id', $batch->id)->count();
                
                return [
                    'id' => (string) $batch->id,
                    'name' => $batch->name,
                    'trainee_count' => $traineeCount,
                    'active' => $batch->status !== 'Archived',
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $batchData,
                'message' => 'Batches retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getBatches error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get trainees for a specific batch
     */
    public function getTraineesByBatch(Request $request, $batchId): JsonResponse
    {
        try {
            $search = $request->input('search', '');
            
            $query = Trainee::where('batch_id', $batchId);
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('roll_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            $trainees = $query->orderBy('name')->get();
            
            $traineeData = $trainees->map(function ($trainee) {
                $attendanceStats = $this->calculateTraineeStats($trainee->id);
                
                return [
                    'id' => (string) $trainee->id,
                    'name' => $trainee->name,
                    'roll_no' => $trainee->roll_number,
                    'batch_id' => $trainee->batch_id,
                    'avatar' => $this->getAvatarInitials($trainee->name),
                    'attendance_percentage' => $attendanceStats['percentage'],
                    'email' => $trainee->email,
                    'phone' => $trainee->phone,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $traineeData,
                'message' => 'Trainees retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getTraineesByBatch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get attendance for a specific trainee
     */
    public function getTraineeAttendance(Request $request, $traineeId): JsonResponse
    {
        try {
            $date = $request->input('date');
            $viewMode = $request->input('view_mode', 'session');
            
            // Get trainee
            $trainee = Trainee::find($traineeId);
            if (!$trainee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainee not found'
                ], 404);
            }
            
            // Get attendance records
            $attendances = $this->getAttendanceRecords($traineeId, $date);
            
            // Format based on view mode
            $attendanceData = $viewMode === 'session' 
                ? $this->formatSessionView($attendances)
                : $this->formatWeeklyView($attendances);
            
            // Get trainee statistics
            $traineeStats = $this->getTraineeStatistics($traineeId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'trainee' => $traineeStats,
                    'attendance' => $attendanceData,
                ],
                'message' => 'Attendance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getTraineeAttendance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get global attendance statistics
     */
    public function getGlobalStats(): JsonResponse
    {
        try {
            $totalTrainees = Trainee::count();
            $totalAttendances = Attendance::count();
            $presentAttendances = Attendance::where('status', 'Present')->count();
            
            $globalPercentage = $totalAttendances > 0 
                ? round(($presentAttendances / $totalAttendances) * 100) 
                : 0;
            
            $todayStats = $this->getTodayStats();
            $weeklyTrend = $this->getWeeklyTrend();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'global_percentage' => $globalPercentage,
                    'total_trainees' => $totalTrainees,
                    'total_attendance_records' => $totalAttendances,
                    'today_attendance' => $todayStats,
                    'weekly_trend' => $weeklyTrend,
                ],
                'message' => 'Global statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getGlobalStats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve global statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Overrule/update attendance status
     */
    public function updateAttendance(Request $request, $attendanceId): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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
            $attendance = Attendance::findOrFail($attendanceId);
            
            $oldStatus = $attendance->status;
            $attendance->status = $request->input('status');
            $attendance->remarks = $request->input('remarks', $attendance->remarks);
            $attendance->marked_at = now();
            $attendance->save();
            
            \Log::info('Attendance overruled', [
                'attendance_id' => $attendanceId,
                'old_status' => $oldStatus,
                'new_status' => $request->input('status'),
                'admin_id' => $request->user()->id,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $attendance->id,
                    'status' => $attendance->status,
                    'updated_at' => $attendance->updated_at,
                ],
                'message' => 'Attendance updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('updateAttendance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Export attendance report
     */
    public function exportReport(Request $request): JsonResponse
    {
        try {
            $batchId = $request->input('batch_id');
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            
            $query = Attendance::with(['trainee', 'timetableSession'])
                ->whereBetween('attendance_date', [$startDate, $endDate]);
            
            if ($batchId) {
                $query->whereHas('trainee', function($q) use ($batchId) {
                    $q->where('batch_id', $batchId);
                });
            }
            
            $attendances = $query->orderBy('attendance_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
            
            $reportData = $attendances->map(function ($attendance) {
                return [
                    'date' => $attendance->attendance_date->format('Y-m-d'),
                    'trainee_name' => $attendance->trainee ? $attendance->trainee->name : 'Unknown',
                    'roll_number' => $attendance->trainee ? $attendance->trainee->roll_number : 'N/A',
                    'course' => $attendance->timetableSession ? $attendance->timetableSession->subject : 'Unknown',
                    'session_time' => $attendance->timetableSession ? $this->formatTimeSlot(
                        $attendance->timetableSession->start_hour,
                        $attendance->timetableSession->duration
                    ) : 'N/A',
                    'status' => $attendance->status,
                    'marked_at' => $attendance->marked_at,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $reportData,
                'message' => 'Report generated successfully',
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_records' => $reportData->count(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('exportReport error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // ========== PRIVATE HELPER METHODS ==========
    
    /**
     * Calculate attendance statistics for a trainee
     */
    private function calculateTraineeStats($traineeId): array
    {
        $totalSessions = Attendance::where('trainee_id', $traineeId)->count();
        $presentSessions = Attendance::where('trainee_id', $traineeId)
            ->where('status', 'Present')
            ->count();
        
        $percentage = $totalSessions > 0 
            ? round(($presentSessions / $totalSessions) * 100) 
            : 0;
        
        return [
            'percentage' => $percentage,
            'total' => $totalSessions,
            'present' => $presentSessions,
        ];
    }
    
    /**
     * Get attendance records for a trainee
     */
    private function getAttendanceRecords($traineeId, $date = null)
    {
        $query = Attendance::where('trainee_id', $traineeId)
            ->with('timetableSession');
        
        if ($date) {
            $query->where('attendance_date', $date);
        }
        
        return $query->orderBy('attendance_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Format attendance data for session view
     */
    private function formatSessionView($attendances): array
    {
        return $attendances->filter(function ($attendance) {
            return $attendance->timetableSession !== null;
        })->map(function ($attendance) {
            return [
                'id' => (string) $attendance->id,
                'date' => $attendance->attendance_date->format('Y-m-d'),
                'session_number' => $this->getSessionNumber($attendance->timetableSession->start_hour),
                'time_slot' => $this->formatTimeSlot(
                    $attendance->timetableSession->start_hour,
                    $attendance->timetableSession->duration
                ),
                'course_name' => $attendance->timetableSession->subject,
                'status' => $attendance->status,
                'verified' => $attendance->marked_at !== null,
            ];
        })->values()->toArray();
    }
    
    /**
     * Format attendance data for weekly view
     */
    private function formatWeeklyView($attendances): array
    {
        $filtered = $attendances->filter(function ($attendance) {
            return $attendance->timetableSession !== null;
        });
        
        $grouped = $filtered->groupBy(function ($attendance) {
            return $attendance->attendance_date->format('Y-m-d');
        });
        
        $result = [];
        foreach ($grouped as $dateKey => $records) {
            $present = $records->where('status', 'Present')->count();
            $total = $records->count();
            
            $result[] = [
                'date' => $dateKey,
                'day_name' => Carbon::parse($dateKey)->format('l'),
                'present' => $present,
                'total' => $total,
                'percentage' => $total > 0 ? round(($present / $total) * 100) : 0,
                'records' => $records->map(function ($record) {
                    return [
                        'id' => (string) $record->id,
                        'session_number' => $this->getSessionNumber($record->timetableSession->start_hour),
                        'time_slot' => $this->formatTimeSlot(
                            $record->timetableSession->start_hour,
                            $record->timetableSession->duration
                        ),
                        'course_name' => $record->timetableSession->subject,
                        'status' => $record->status,
                    ];
                })->values(),
            ];
        }
        
        return $result;
    }
    
    /**
     * Get trainee statistics including breakdown
     */
    private function getTraineeStatistics($traineeId): array
    {
        $trainee = Trainee::find($traineeId);
        
        $totalSessions = Attendance::where('trainee_id', $traineeId)->count();
        $presentSessions = Attendance::where('trainee_id', $traineeId)
            ->where('status', 'Present')
            ->count();
        
        $overallPercentage = $totalSessions > 0 
            ? round(($presentSessions / $totalSessions) * 100) 
            : 0;
        
        return [
            'id' => (string) $trainee->id,
            'name' => $trainee->name,
            'roll_no' => $trainee->roll_number,
            'batch_id' => $trainee->batch_id,
            'avatar' => $this->getAvatarInitials($trainee->name),
            'attendance_percentage' => $overallPercentage,
            'total_sessions' => $totalSessions,
            'present_sessions' => $presentSessions,
            'absent_sessions' => Attendance::where('trainee_id', $traineeId)
                ->where('status', 'Absent')
                ->count(),
            'on_leave_sessions' => Attendance::where('trainee_id', $traineeId)
                ->where('status', 'On Leave')
                ->count(),
        ];
    }
    
    /**
     * Get today's attendance statistics
     */
    private function getTodayStats(): array
    {
        $today = Carbon::today();
        $todayAttendances = Attendance::whereDate('attendance_date', $today)->count();
        $todayPresent = Attendance::whereDate('attendance_date', $today)
            ->where('status', 'Present')
            ->count();
        
        return [
            'total' => $todayAttendances,
            'present' => $todayPresent,
            'percentage' => $todayAttendances > 0 
                ? round(($todayPresent / $todayAttendances) * 100) 
                : 0,
        ];
    }
    
    /**
     * Get weekly attendance trend
     */
    private function getWeeklyTrend(): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayAttendances = Attendance::whereDate('attendance_date', $date)->count();
            $dayPresent = Attendance::whereDate('attendance_date', $date)
                ->where('status', 'Present')
                ->count();
            
            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'attendance_count' => $dayAttendances,
                'present_count' => $dayPresent,
                'percentage' => $dayAttendances > 0 
                    ? round(($dayPresent / $dayAttendances) * 100) 
                    : 0,
            ];
        }
        
        return $trend;
    }
    
    /**
     * Get avatar initials from name
     */
    private function getAvatarInitials($name): string
    {
        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
    
    /**
     * Get session number from start hour
     */
    private function getSessionNumber($startHour): int
    {
        $mapping = [
            9 => 1, 10 => 2, 11 => 3, 12 => 4, 13 => 5, 14 => 6,
        ];
        return $mapping[$startHour] ?? 1;
    }
    
    /**
     * Format time slot from start hour and duration
     */
    private function formatTimeSlot($startHour, $duration): string
    {
        $endHour = $startHour + $duration;
        $startFormat = $startHour >= 12 
            ? ($startHour == 12 ? 12 : $startHour - 12) . ':00 PM' 
            : $startHour . ':00 AM';
        $endFormat = $endHour >= 12 
            ? ($endHour == 12 ? 12 : $endHour - 12) . ':00 PM' 
            : $endHour . ':00 AM';
        return "{$startFormat} - {$endFormat}";
    }
}