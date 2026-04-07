<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\Trainee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * Get leave requests for authenticated user (Trainee view)
     */
    // public function myLeaves(Request $request): JsonResponse
    // {
    //     try {
    //         $user = $request->user();
            
    //         // Get trainee record for this user
    //         $trainee = Trainee::where('user_id', $user->id)->first();
            
    //         if (!$trainee) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Trainee profile not found'
    //             ], 404);
    //         }
            
    //         $leaves = LeaveRequest::where('trainee_id', $trainee->id)
    //             ->orderBy('created_at', 'desc')
    //             ->get();
            
    //         $leaveData = $leaves->map(function ($leave) {
    //             $startDate = $leave->start_date instanceof Carbon ? $leave->start_date : Carbon::parse($leave->start_date);
    //             $endDate = $leave->end_date instanceof Carbon ? $leave->end_date : Carbon::parse($leave->end_date);
                
    //             return [
    //                 'id' => $leave->id,
    //                 'type' => $leave->leave_type,
    //                 'from' => $startDate->format('Y-m-d'),
    //                 'to' => $endDate->format('Y-m-d'),
    //                 'duration' => $leave->duration_days,
    //                 'reason' => $leave->reason,
    //                 'status' => $leave->status,
    //                 'rejection_reason' => $leave->rejection_reason,
    //                 'approved_by' => $leave->approver ? $leave->approver->name : null,
    //                 'approved_at' => $leave->approved_at ? Carbon::parse($leave->approved_at)->format('Y-m-d H:i:s') : null,
    //                 'created_at' => Carbon::parse($leave->created_at)->format('Y-m-d H:i:s'),
    //             ];
    //         });
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => $leaveData,
    //             'message' => 'Your leave requests retrieved successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve your leave requests',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
    // app/Http/Controllers/Api/LeaveController.php

        /**
     * Get leave requests for authenticated user (Trainee view)
     */
    public function myLeaves(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Get trainee record for this user
            $trainee = Trainee::where('user_id', $user->id)->first();
            
            if (!$trainee) {
                // Return empty array instead of error for non-trainees
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No trainee profile found'
                ]);
            }
            
            $leaves = LeaveRequest::where('trainee_id', $trainee->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $leaveData = $leaves->map(function ($leave) {
                $startDate = $leave->start_date instanceof Carbon ? $leave->start_date : Carbon::parse($leave->start_date);
                $endDate = $leave->end_date instanceof Carbon ? $leave->end_date : Carbon::parse($leave->end_date);
                
                return [
                    'id' => $leave->id,
                    'type' => $leave->leave_type,
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d'),
                    'duration' => $leave->duration_days,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'rejection_reason' => $leave->rejection_reason,
                    'approved_by' => $leave->approver ? $leave->approver->name : null,
                    'approved_at' => $leave->approved_at ? Carbon::parse($leave->approved_at)->format('Y-m-d H:i:s') : null,
                    'created_at' => Carbon::parse($leave->created_at)->format('Y-m-d H:i:s'),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $leaveData,
                'message' => 'Your leave requests retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('myLeaves error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your leave requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

// public function myLeaves(Request $request): JsonResponse
// {
//     try {
//         $user = $request->user();
        
//         // Get trainee record for this user
//         $trainee = Trainee::where('user_id', $user->id)->first();
        
//         if (!$trainee) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Trainee profile not found'
//             ], 404);
//         }
        
//         $leaves = LeaveRequest::where('trainee_id', $trainee->id)
//             ->orderBy('created_at', 'desc')
//             ->get();
        
//         $leaveData = $leaves->map(function ($leave) {
//             $startDate = $leave->start_date instanceof Carbon ? $leave->start_date : Carbon::parse($leave->start_date);
//             $endDate = $leave->end_date instanceof Carbon ? $leave->end_date : Carbon::parse($leave->end_date);
            
//             return [
//                 'id' => $leave->id,
//                 'type' => $leave->leave_type,
//                 'from' => $startDate->format('Y-m-d'),
//                 'to' => $endDate->format('Y-m-d'),
//                 'duration' => $leave->duration_days,
//                 'reason' => $leave->reason,
//                 'status' => $leave->status,
//                 'rejection_reason' => $leave->rejection_reason,
//                 'approved_by' => $leave->approver ? $leave->approver->name : null,
//                 'approved_at' => $leave->approved_at ? Carbon::parse($leave->approved_at)->format('Y-m-d H:i:s') : null,
//                 'created_at' => Carbon::parse($leave->created_at)->format('Y-m-d H:i:s'),
//             ];
//         });
        
//         return response()->json([
//             'success' => true,
//             'data' => $leaveData,
//             'message' => 'Your leave requests retrieved successfully'
//         ]);
//     } catch (\Exception $e) {
//         \Log::error('myLeaves error: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to retrieve your leave requests',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

    /**
     * Get leave balance for authenticated user
     */
//  public function myBalance(Request $request): JsonResponse
// {
//     try {
//         $user = $request->user();
        
//         \Log::info('myBalance called', ['user_id' => $user->id]);
        
//         // Use the fixed getOrCreate method
//         $balance = LeaveBalance::getOrCreate($user->id);
        
//         // Update pending days count
//         $trainee = Trainee::where('user_id', $user->id)->first();
        
//         if ($trainee) {
//             $pendingDays = LeaveRequest::where('trainee_id', $trainee->id)
//                 ->where('status', 'Pending')
//                 ->sum('duration_days');
            
//             $balance->pending_days = $pendingDays;
//             $balance->save();
//         }
        
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'total' => $balance->total_days,
//                 'used' => $balance->used_days,
//                 'pending' => $balance->pending_days,
//                 'available' => $balance->remaining_days,
//             ],
//             'message' => 'Leave balance retrieved successfully'
//         ]);
//     } catch (\Exception $e) {
//         \Log::error('myBalance error: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to retrieve leave balance',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }    
    /**
     * Get leave balance for authenticated user
     */
    public function myBalance(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            \Log::info('myBalance called', ['user_id' => $user->id, 'user_role' => $user->role]);
            
            // If user is not a trainee, return default balance
            if ($user->role !== 'trainee') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total' => 0,
                        'used' => 0,
                        'pending' => 0,
                        'available' => 0,
                    ],
                    'message' => 'Leave balance not applicable for non-trainee users'
                ]);
            }
            
            // Get trainee profile
            $trainee = Trainee::where('user_id', $user->id)->first();
            
            if (!$trainee) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total' => 12,
                        'used' => 0,
                        'pending' => 0,
                        'available' => 12,
                    ],
                    'message' => 'Trainee profile pending. Using default balance.'
                ]);
            }
            
            // Get or create leave balance
            $balance = LeaveBalance::where('user_id', $user->id)
                ->where('year', Carbon::now()->year)
                ->first();
            
            if (!$balance) {
                $balance = LeaveBalance::create([
                    'user_id' => $user->id,
                    'trainee_id' => $trainee->id,
                    'total_days' => 12,
                    'used_days' => 0,
                    'pending_days' => 0,
                    'year' => Carbon::now()->year,
                ]);
            }
            
            // Update pending days count
            $pendingDays = LeaveRequest::where('trainee_id', $trainee->id)
                ->where('status', 'Pending')
                ->sum('duration_days');
            
            $balance->pending_days = $pendingDays;
            $balance->save();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $balance->total_days,
                    'used' => $balance->used_days,
                    'pending' => $balance->pending_days,
                    'available' => $balance->total_days - $balance->used_days - $balance->pending_days,
                ],
                'message' => 'Leave balance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('myBalance error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new leave request
     */

    // app/Http/Controllers/Api/LeaveController.php

/**
 * Create a new leave request
 */
// public function store(Request $request): JsonResponse
// {
//     $validator = Validator::make($request->all(), [
//         'leave_type' => ['required', Rule::in(['Medical', 'Personal', 'Earned', 'Casual', 'Study'])],
//         'start_date' => 'required|date|after_or_equal:today',
//         'end_date' => 'required|date|after_or_equal:start_date',
//         'reason' => 'required|string|min:10|max:1000',
//     ]);
    
//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors' => $validator->errors(),
//             'message' => 'Validation failed'
//         ], 422);
//     }
    
//     try {
//         $user = $request->user();
        
//         \Log::info('Creating leave request', ['user_id' => $user->id, 'user_email' => $user->email]);
        
//         // Calculate duration
//         $start = Carbon::parse($request->start_date);
//         $end = Carbon::parse($request->end_date);
//         $duration = $start->diffInDays($end) + 1;
        
//         // Get or create trainee profile (optional, but good to have)
//         $trainee = Trainee::where('user_id', $user->id)->first();
        
//         if (!$trainee) {
//             // Create trainee profile if it doesn't exist
//             $trainee = Trainee::create([
//                 'user_id' => $user->id,
//                 'roll_number' => 'TR-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
//                 'name' => $user->name,
//                 'email' => $user->email,
//                 'service_type' => 'IFS',
//                 'enrollment_status' => 'Enrolled',
//                 'gender' => 'Male',
//             ]);
//             \Log::info('Created trainee profile', ['trainee_id' => $trainee->id]);
//         }
        
//         // Get current balance (using user_id)
//         $balance = LeaveBalance::getOrCreate($user->id);
        
//         // Check if enough balance available
//         if ($balance->remaining_days < $duration) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Insufficient leave balance',
//                 'data' => [
//                     'available' => $balance->remaining_days,
//                     'requested' => $duration,
//                 ]
//             ], 422);
//         }
        
//         // Check for overlapping leave requests
//         $overlap = LeaveRequest::where('user_id', $user->id)  // Use user_id
//             ->where('status', '!=', 'Rejected')
//             ->where(function($query) use ($request) {
//                 $query->whereBetween('start_date', [$request->start_date, $request->end_date])
//                       ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
//                       ->orWhere(function($q) use ($request) {
//                           $q->where('start_date', '<=', $request->start_date)
//                             ->where('end_date', '>=', $request->end_date);
//                       });
//             })
//             ->exists();
            
//         if ($overlap) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'You already have a leave request overlapping with these dates'
//             ], 422);
//         }
        
//         // Create leave request with user_id (required) and trainee_id (optional)
//         $leave = LeaveRequest::create([
//             'user_id' => $user->id,           // REQUIRED - NOT NULL
//             'trainee_id' => $trainee->id,      // Optional - can be NULL
//             'leave_type' => $request->leave_type,
//             'start_date' => $request->start_date,
//             'end_date' => $request->end_date,
//             'duration_days' => $duration,
//             'reason' => $request->reason,
//             'status' => 'Pending',
//         ]);
        
//         \Log::info('Leave request created', ['leave_id' => $leave->id]);
        
//         // Update pending days in balance
//         $balance->addPendingDays($duration);
        
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'id' => $leave->id,
//                 'type' => $leave->leave_type,
//                 'from' => $leave->start_date,
//                 'to' => $leave->end_date,
//                 'duration' => $leave->duration_days,
//                 'reason' => $leave->reason,
//                 'status' => $leave->status,
//             ],
//             'message' => 'Leave request submitted successfully'
//         ], 201);
//     } catch (\Exception $e) {
//         \Log::error('store error: ' . $e->getMessage());
//         \Log::error($e->getTraceAsString());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to create leave request',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

/**
 * Create a new leave request
 */
// public function store(Request $request): JsonResponse
// {
//     $validator = Validator::make($request->all(), [
//         'leave_type' => ['required', Rule::in(['Medical', 'Personal', 'Earned', 'Casual', 'Study'])],
//         'start_date' => 'required|date|after_or_equal:today',
//         'end_date' => 'required|date|after_or_equal:start_date',
//         'reason' => 'required|string|min:10|max:1000',
//     ]);
    
//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors' => $validator->errors(),
//             'message' => 'Validation failed'
//         ], 422);
//     }
    
//     try {
//         $user = $request->user();
        
//         // Only trainees can apply for leave
//         if ($user->role !== 'trainee') {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Only trainees can apply for leave'
//             ], 403);
//         }
        
//         // Get trainee profile
//         $trainee = Trainee::where('user_id', $user->id)->first();
        
//         if (!$trainee) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Trainee profile not found'
//             ], 404);
//         }
        
//         // Calculate duration
//         $start = Carbon::parse($request->start_date);
//         $end = Carbon::parse($request->end_date);
//         $duration = $start->diffInDays($end) + 1;
        
//         // Get current balance
//         $balance = LeaveBalance::getOrCreate($user->id);
        
//         // Check if enough balance available
//         if ($balance->remaining_days < $duration) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Insufficient leave balance',
//                 'data' => [
//                     'available' => $balance->remaining_days,
//                     'requested' => $duration,
//                 ]
//             ], 422);
//         }
        
//         // Check for overlapping leave requests
//         $overlap = LeaveRequest::where('user_id', $user->id)
//             ->where('status', '!=', 'Rejected')
//             ->where(function($query) use ($request) {
//                 $query->whereBetween('start_date', [$request->start_date, $request->end_date])
//                       ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
//                       ->orWhere(function($q) use ($request) {
//                           $q->where('start_date', '<=', $request->start_date)
//                             ->where('end_date', '>=', $request->end_date);
//                       });
//             })
//             ->exists();
            
//         if ($overlap) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'You already have a leave request overlapping with these dates'
//             ], 422);
//         }
        
//         DB::beginTransaction();
        
//         // Create leave request
//         $leave = LeaveRequest::create([
//             'user_id' => $user->id,
//             'trainee_id' => $trainee->id,
//             'leave_type' => $request->leave_type,
//             'start_date' => $request->start_date,
//             'end_date' => $request->end_date,
//             'duration_days' => $duration,
//             'reason' => $request->reason,
//             'status' => 'Pending',
//         ]);
        
//         // Update pending days in balance
//         if (method_exists($balance, 'addPendingDays')) {
//             $balance->addPendingDays($duration);
//         }
        
//         DB::commit();
        
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'id' => $leave->id,
//                 'type' => $leave->leave_type,
//                 'from' => $leave->start_date,
//                 'to' => $leave->end_date,
//                 'duration' => $leave->duration_days,
//                 'reason' => $leave->reason,
//                 'status' => $leave->status,
//             ],
//             'message' => 'Leave request submitted successfully'
//         ], 201);
//     } catch (\Exception $e) {
//         DB::rollBack();
//         \Log::error('store error: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to create leave request',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }



public function store(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'leave_type' => ['required', Rule::in(['Medical', 'Personal', 'Earned', 'Casual', 'Study'])],
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after_or_equal:start_date',
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
        // Get authenticated user
        $user = $request->user();
        
        // Debug: Log user info
        \Log::info('store method called', [
            'user_exists' => $user ? true : false,
            'user_id' => $user ? $user->id : null,
            'user_role' => $user ? $user->role : null,
            'request_data' => $request->all()
        ]);
        
        // Check if user is authenticated
        if (!$user) {
            \Log::warning('User not authenticated for leave request');
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated. Please log in.'
            ], 401);
        }
        
        // Only trainees can apply for leave
        if ($user->role !== 'trainee') {
            \Log::warning('Non-trainee user attempted to apply for leave', [
                'user_id' => $user->id,
                'user_role' => $user->role
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Only trainees can apply for leave'
            ], 403);
        }
        
        // Calculate duration
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $duration = $start->diffInDays($end) + 1;
        
        // Get or create trainee profile
        $trainee = Trainee::where('user_id', $user->id)->first();
        
        if (!$trainee) {
            // Create trainee profile if it doesn't exist
            \Log::info('Creating trainee profile for user', ['user_id' => $user->id]);
            $trainee = Trainee::create([
                'user_id' => $user->id,
                'roll_number' => 'TR-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'name' => $user->name,
                'email' => $user->email,
                'service_type' => 'IFS',
                'enrollment_status' => 'Enrolled',
                'gender' => 'Male',
            ]);
        }
        
        // Get current balance
        $balance = LeaveBalance::getOrCreate($user->id);
        
        // Check if enough balance available
        if ($balance->remaining_days < $duration) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient leave balance',
                'data' => [
                    'available' => $balance->remaining_days,
                    'requested' => $duration,
                ]
            ], 422);
        }
        
        // Check for overlapping leave requests
        $overlap = LeaveRequest::where('user_id', $user->id)
            ->where('status', '!=', 'Rejected')
            ->where(function($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })
            ->exists();
            
        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a leave request overlapping with these dates'
            ], 422);
        }
        
        DB::beginTransaction();
        
        // Create leave request
        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'trainee_id' => $trainee->id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'duration_days' => $duration,
            'reason' => $request->reason,
            'status' => 'Pending',
        ]);
        
        // Update pending days in balance
        if (method_exists($balance, 'addPendingDays')) {
            $balance->addPendingDays($duration);
        }
        
        DB::commit();
        
        \Log::info('Leave request created successfully', [
            'leave_id' => $leave->id,
            'user_id' => $user->id,
            'duration' => $duration
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $leave->id,
                'type' => $leave->leave_type,
                'from' => $leave->start_date,
                'to' => $leave->end_date,
                'duration' => $leave->duration_days,
                'reason' => $leave->reason,
                'status' => $leave->status,
            ],
            'message' => 'Leave request submitted successfully'
        ], 201);
        
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('store error: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => 'Failed to create leave request',
            'error' => $e->getMessage()
        ], 500);
    }
}

//  public function store(Request $request): JsonResponse
// {
//     $validator = Validator::make($request->all(), [
//         'leave_type' => ['required', Rule::in(['Medical', 'Personal', 'Earned', 'Casual', 'Study'])],
//         'start_date' => 'required|date|after_or_equal:today',
//         'end_date' => 'required|date|after_or_equal:start_date',
//         'reason' => 'required|string|min:10|max:1000',
//     ]);
    
//     if ($validator->fails()) {
//         return response()->json([
//             'success' => false,
//             'errors' => $validator->errors(),
//             'message' => 'Validation failed'
//         ], 422);
//     }
    
//     try {
//         $user = $request->user();
        
//         // Get or create trainee profile
//         $trainee = Trainee::where('user_id', $user->id)->first();
        
//         if (!$trainee) {
//             // Create trainee profile if it doesn't exist
//             $trainee = Trainee::create([
//                 'user_id' => $user->id,
//                 'roll_number' => 'TR-' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
//                 'name' => $user->name,
//                 'email' => $user->email,
//                 'service_type' => 'IFS',
//                 'enrollment_status' => 'Enrolled',
//                 'gender' => 'Male',
//             ]);
//         }
        
//         // Calculate duration
//         $start = Carbon::parse($request->start_date);
//         $end = Carbon::parse($request->end_date);
//         $duration = $start->diffInDays($end) + 1;
        
//         // Get current balance (using user_id)
//         $balance = LeaveBalance::getOrCreate($user->id);
        
//         // Check if enough balance available
//         if ($balance->remaining_days < $duration) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Insufficient leave balance',
//                 'data' => [
//                     'available' => $balance->remaining_days,
//                     'requested' => $duration,
//                 ]
//             ], 422);
//         }
        
//         // Check for overlapping leave requests
//         $overlap = LeaveRequest::where('trainee_id', $trainee->id)
//             ->where('status', '!=', 'Rejected')
//             ->where(function($query) use ($request) {
//                 $query->whereBetween('start_date', [$request->start_date, $request->end_date])
//                       ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
//                       ->orWhere(function($q) use ($request) {
//                           $q->where('start_date', '<=', $request->start_date)
//                             ->where('end_date', '>=', $request->end_date);
//                       });
//             })
//             ->exists();
            
//         if ($overlap) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'You already have a leave request overlapping with these dates'
//             ], 422);
//         }
        
//         // Create leave request
//         $leave = LeaveRequest::create([
//             'trainee_id' => $trainee->id,
//             'leave_type' => $request->leave_type,
//             'start_date' => $request->start_date,
//             'end_date' => $request->end_date,
//             'duration_days' => $duration,
//             'reason' => $request->reason,
//             'status' => 'Pending',
//         ]);
        
//         // Update pending days in balance
//         $balance->addPendingDays($duration);
        
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'id' => $leave->id,
//                 'type' => $leave->leave_type,
//                 'from' => $leave->start_date,
//                 'to' => $leave->end_date,
//                 'duration' => $leave->duration_days,
//                 'reason' => $leave->reason,
//                 'status' => $leave->status,
//             ],
//             'message' => 'Leave request submitted successfully'
//         ], 201);
//     } catch (\Exception $e) {
//         \Log::error('store error: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to create leave request',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }
    
    /**
     * Cancel a leave request (Trainee can cancel pending requests)
     */
    public function cancel($id, Request $request): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($id);
            $user = $request->user();
            
            $trainee = Trainee::where('user_id', $user->id)->first();
            
            // Check if this leave belongs to the authenticated trainee
            if ($leave->trainee_id !== $trainee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to cancel this leave request'
                ], 403);
            }
            
            // Only pending requests can be cancelled
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending leave requests can be cancelled'
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Update leave status
            $leave->status = 'Cancelled';
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
                'message' => 'Leave request cancelled successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get leave statistics for dashboard (role-based)
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $isAdmin = in_array($user->role, ['admin', 'course_director']);
            
            if ($isAdmin) {
                // Admin view - all trainees
                $pending = LeaveRequest::pending()->count();
                $approved = LeaveRequest::approved()->count();
                $rejected = LeaveRequest::rejected()->count();
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pending' => $pending,
                        'approved' => $approved,
                        'rejected' => $rejected,
                        'total' => LeaveRequest::count(),
                    ],
                    'message' => 'Leave statistics retrieved successfully'
                ]);
            } else {
                // Trainee view - their own statistics
                $trainee = Trainee::where('user_id', $user->id)->first();
                
                if (!$trainee) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trainee profile not found'
                    ], 404);
                }
                
                $balance = LeaveBalance::where('trainee_id', $trainee->id)
                    ->where('year', Carbon::now()->year)
                    ->first();
                
                $pending = LeaveRequest::where('trainee_id', $trainee->id)->pending()->count();
                $approved = LeaveRequest::where('trainee_id', $trainee->id)->approved()->count();
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_allocation' => $balance ? $balance->total_days : 12,
                        'used' => $balance ? $balance->used_days : 0,
                        'pending' => $balance ? $balance->pending_days : 0,
                        'available' => $balance ? ($balance->total_days - $balance->used_days - $balance->pending_days) : 12,
                        'pending_requests' => $pending,
                        'approved_requests' => $approved,
                    ],
                    'message' => 'Leave statistics retrieved successfully'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // ADMIN METHODS
 
    

    
    /**
     * ADMIN ONLY - Approve a leave request
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($id);
            
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
            
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave request has already been processed'
                ], 422);
            }
            
            DB::beginTransaction();
            
            $leave->status = 'Approved';
            $leave->approved_by = $request->user()->id ?? null;
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
                'message' => 'Leave request approved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ADMIN ONLY - Reject a leave request
     */
    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $leave = LeaveRequest::findOrFail($id);
            
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
            
            if ($leave->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave request has already been processed'
                ], 422);
            }
            
            DB::beginTransaction();
            
            $leave->status = 'Rejected';
            $leave->approved_by = $request->user()->id ?? null;
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
                'message' => 'Leave request rejected successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}