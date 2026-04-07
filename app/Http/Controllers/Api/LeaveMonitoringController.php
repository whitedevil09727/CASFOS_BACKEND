<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Trainee;
use App\Models\Batch;
use App\Models\User;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class LeaveMonitoringController extends Controller
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
                // Get pending leave count for this trainee
                $pendingLeaves = LeaveRequest::where('trainee_id', $trainee->id)
                    ->where('status', 'Pending')
                    ->count();
                
                return [
                    'id' => (string) $trainee->id,
                    'name' => $trainee->name,
                    'roll_no' => $trainee->roll_number,
                    'batch_id' => $trainee->batch_id,
                    'avatar' => $this->getAvatarInitials($trainee->name),
                    'pending_leaves' => $pendingLeaves,
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
     * Get leave requests for a specific trainee
     */
    public function getTraineeLeaves(Request $request, $traineeId): JsonResponse
    {
        try {
            $statusFilter = $request->input('status', 'All');
            
            $query = LeaveRequest::where('trainee_id', $traineeId)
                ->with(['user', 'approver'])
                ->orderBy('created_at', 'desc');
            
            if ($statusFilter !== 'All') {
                $query->where('status', $statusFilter);
            }
            
            $leaves = $query->get();
            
            $trainee = Trainee::find($traineeId);
            
            $leaveData = $leaves->map(function ($leave) {
                return [
                    'id' => (string) $leave->id,
                    'trainee_id' => (string) $leave->trainee_id,
                    'trainee_name' => $leave->trainee ? $leave->trainee->name : 'Unknown',
                    'trainee_roll_no' => $leave->trainee ? $leave->trainee->roll_number : 'N/A',
                    'type' => $leave->leave_type,
                    'from_date' => $leave->start_date->format('Y-m-d'),
                    'to_date' => $leave->end_date->format('Y-m-d'),
                    'duration_days' => $leave->duration_days,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'applied_on' => $leave->created_at->format('Y-m-d'),
                    'rejection_reason' => $leave->rejection_reason,
                    'approved_by' => $leave->approver ? $leave->approver->name : null,
                    'approved_at' => $leave->approved_at ? $leave->approved_at->format('Y-m-d H:i:s') : null,
                ];
            });
            
            // Calculate summary
            $summary = [
                'total' => $leaves->count(),
                'pending' => $leaves->where('status', 'Pending')->count(),
                'approved' => $leaves->where('status', 'Approved')->count(),
                'rejected' => $leaves->where('status', 'Rejected')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'leaves' => $leaveData,
                    'summary' => $summary,
                    'trainee' => $trainee ? [
                        'id' => (string) $trainee->id,
                        'name' => $trainee->name,
                        'roll_no' => $trainee->roll_number,
                        'avatar' => $this->getAvatarInitials($trainee->name),
                    ] : null,
                ],
                'message' => 'Leave requests retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getTraineeLeaves error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Approve a leave request (Admin/CD only)
     */
    public function approveLeave(Request $request, $leaveId): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($leaveId);
            
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Check if already processed
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave request has already been processed'
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Update leave status
            $leave->status = 'Approved';
            $leave->approved_by = $request->user()->id;
            $leave->approved_at = now();
            $leave->notes = $request->notes;
            $leave->save();
            
            // Update leave balance
            $balance = LeaveBalance::where('trainee_id', $leave->trainee_id)
                ->where('year', Carbon::parse($leave->start_date)->year)
                ->first();
            
            if ($balance) {
                $balance->used_days += $leave->duration_days;
                $balance->pending_days -= $leave->duration_days;
                $balance->save();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $leave->id,
                    'status' => $leave->status,
                ],
                'message' => 'Leave request approved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('approveLeave error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject a leave request (Admin/CD only)
     */
    public function rejectLeave(Request $request, $leaveId): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($leaveId);
            
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|min:5|max:500',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Check if already processed
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave request has already been processed'
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Update leave status
            $leave->status = 'Rejected';
            $leave->approved_by = $request->user()->id;
            $leave->approved_at = now();
            $leave->rejection_reason = $request->rejection_reason;
            $leave->save();
            
            // Update leave balance (remove pending days)
            $balance = LeaveBalance::where('trainee_id', $leave->trainee_id)
                ->where('year', Carbon::parse($leave->start_date)->year)
                ->first();
            
            if ($balance) {
                $balance->pending_days -= $leave->duration_days;
                $balance->save();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $leave->id,
                    'status' => $leave->status,
                ],
                'message' => 'Leave request rejected successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('rejectLeave error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get leave statistics for dashboard
     */
    public function getStats(): JsonResponse
    {
        try {
            $pending = LeaveRequest::pending()->count();
            $approved = LeaveRequest::approved()->count();
            $rejected = LeaveRequest::rejected()->count();
            $total = LeaveRequest::count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'total' => $total,
                ],
                'message' => 'Leave statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('getStats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Export leave reports
     */
    public function exportReport(Request $request): JsonResponse
    {
        try {
            $batchId = $request->input('batch_id');
            $status = $request->input('status');
            $startDate = $request->input('start_date', Carbon::now()->subMonths(3)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            
            $query = LeaveRequest::with(['trainee', 'approver'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            
            if ($batchId) {
                $query->whereHas('trainee', function($q) use ($batchId) {
                    $q->where('batch_id', $batchId);
                });
            }
            
            if ($status && $status !== 'All') {
                $query->where('status', $status);
            }
            
            $leaves = $query->orderBy('created_at', 'desc')->get();
            
            $reportData = $leaves->map(function ($leave) {
                return [
                    'id' => $leave->id,
                    'trainee_name' => $leave->trainee ? $leave->trainee->name : 'Unknown',
                    'roll_number' => $leave->trainee ? $leave->trainee->roll_number : 'N/A',
                    'leave_type' => $leave->leave_type,
                    'start_date' => $leave->start_date->format('Y-m-d'),
                    'end_date' => $leave->end_date->format('Y-m-d'),
                    'duration' => $leave->duration_days,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'applied_on' => $leave->created_at->format('Y-m-d'),
                    'approved_by' => $leave->approver ? $leave->approver->name : null,
                    'approved_on' => $leave->approved_at ? $leave->approved_at->format('Y-m-d') : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $reportData,
                'meta' => [
                    'total' => $reportData->count(),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'message' => 'Report generated successfully'
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
    
    // Helper methods
    private function getAvatarInitials($name): string
    {
        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
}