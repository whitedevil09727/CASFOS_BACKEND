<?php
// app/Http/Controllers/Api/TraineeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Trainee;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TraineeController extends Controller
{
    /**
     * Get all trainees
     */
    public function index(): JsonResponse
    {
        try {
            $trainees = Trainee::with('user')
                ->orderBy('name')
                ->get();
            
            // Transform data for frontend
            $traineeData = $trainees->map(function ($trainee) {
                return [
                    'id' => $trainee->id,
                    'name' => $trainee->name,
                    'username' => $trainee->user ? $trainee->user->username : '',
                    'email' => $trainee->email,
                    'regNo' => $trainee->roll_number,
                    'batchId' => '', // You can add batch_id column if needed
                    'batchLabel' => '',
                    'course' => '',
                    'service' => $trainee->service_type,
                    'group' => '', // You can add group column if needed
                    'status' => $this->mapEnrollmentStatus($trainee->enrollment_status),
                    'gender' => $trainee->gender,
                    'phone' => $trainee->phone,
                    'enrollment_status' => $trainee->enrollment_status,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $traineeData,
                'message' => 'Trainees retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Map enrollment status to frontend status
     */
    private function mapEnrollmentStatus($status): string
    {
        $map = [
            'Enrolled' => 'Active',
            'Pending' => 'Pending Review',
            'Withdrawn' => 'Completed',
            'All' => 'Active',
        ];
        
        return $map[$status] ?? 'Active';
    }
    
    /**
     * Map frontend status to enrollment status
     */
    private function mapStatusToEnrollment($status): string
    {
        $map = [
            'Active' => 'Enrolled',
            'Field Assignment' => 'Enrolled',
            'Pending Review' => 'Pending',
            'Completed' => 'Withdrawn',
        ];
        
        return $map[$status] ?? 'Enrolled';
    }
    
    /**
     * Get single trainee
     */
    public function show($id): JsonResponse
    {
        try {
            $trainee = Trainee::with('user')->findOrFail($id);
            
            $traineeData = [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'username' => $trainee->user ? $trainee->user->username : '',
                'email' => $trainee->email,
                'regNo' => $trainee->roll_number,
                'service' => $trainee->service_type,
                'status' => $this->mapEnrollmentStatus($trainee->enrollment_status),
                'gender' => $trainee->gender,
                'phone' => $trainee->phone,
                'enrollment_status' => $trainee->enrollment_status,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $traineeData,
                'message' => 'Trainee retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trainee not found'
            ], 404);
        }
    }
    
    /**
     * Create a new trainee
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|unique:trainees,email|unique:users,email',
            'password' => 'required|string|min:6',
            'regNo' => 'required|string|max:50|unique:trainees,roll_number',
            'service' => 'required|string|in:IFS,SFS,Other',
            'status' => ['required', Rule::in(['Active', 'Field Assignment', 'Pending Review', 'Completed'])],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'phone' => 'nullable|string|max:20',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        try {
            // Create user account for authentication
            $user = User::create([
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_TRAINEE,
                'email_verified_at' => now(),
            ]);
            
            // Map status to enrollment_status
            $enrollmentStatus = $this->mapStatusToEnrollment($request->status);
            
            // Create trainee record
            $trainee = Trainee::create([
                'roll_number' => $request->regNo,
                'name' => $request->name,
                'gender' => $request->gender,
                'service_type' => $request->service,
                'enrollment_status' => $enrollmentStatus,
                'email' => $request->email,
                'phone' => $request->phone,
                'user_id' => $user->id,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $trainee->id,
                    'name' => $trainee->name,
                    'username' => $user->username,
                    'email' => $trainee->email,
                    'regNo' => $trainee->roll_number,
                    'service' => $trainee->service_type,
                    'status' => $request->status,
                    'gender' => $trainee->gender,
                    'phone' => $trainee->phone,
                ],
                'message' => 'Trainee created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create trainee',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update trainee
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $trainee = Trainee::with('user')->findOrFail($id);
            $user = $trainee->user;
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:50|unique:users,username,' . ($user ? $user->id : 'NULL'),
                'email' => 'required|email|unique:trainees,email,' . $id . '|unique:users,email,' . ($user ? $user->id : 'NULL'),
                'password' => 'nullable|string|min:6',
                'regNo' => 'required|string|max:50|unique:trainees,roll_number,' . $id,
                'service' => 'required|string|in:IFS,SFS,Other',
                'status' => ['required', Rule::in(['Active', 'Field Assignment', 'Pending Review', 'Completed'])],
                'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
                'phone' => 'nullable|string|max:20',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Map status to enrollment_status
            $enrollmentStatus = $this->mapStatusToEnrollment($request->status);
            
            // Update trainee record
            $trainee->update([
                'roll_number' => $request->regNo,
                'name' => $request->name,
                'gender' => $request->gender,
                'service_type' => $request->service,
                'enrollment_status' => $enrollmentStatus,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);
            
            // Update user account if exists
            if ($user) {
                $userData = [
                    'username' => $request->username,
                    'name' => $request->name,
                    'email' => $request->email,
                ];
                
                if ($request->filled('password')) {
                    $userData['password'] = Hash::make($request->password);
                }
                
                $user->update($userData);
            } else {
                // Create user account if it doesn't exist
                User::create([
                    'username' => $request->username,
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password ?? 'password123'),
                    'role' => User::ROLE_TRAINEE,
                    'email_verified_at' => now(),
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $trainee->id,
                    'name' => $trainee->name,
                    'username' => $user ? $user->username : $request->username,
                    'email' => $trainee->email,
                    'regNo' => $trainee->roll_number,
                    'service' => $trainee->service_type,
                    'status' => $request->status,
                    'gender' => $trainee->gender,
                    'phone' => $trainee->phone,
                ],
                'message' => 'Trainee updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update trainee',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete trainee
     */
    public function destroy($id): JsonResponse
    {
        try {
            $trainee = Trainee::with('user')->findOrFail($id);
            
            // Soft delete trainee
            $trainee->delete();
            
            // Also soft delete the associated user if exists
            if ($trainee->user) {
                $trainee->user->delete();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Trainee deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete trainee',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available batches for dropdown (from your batches table)
     */
    public function getBatches(): JsonResponse
    {
        try {
            $batches = Batch::where('status', '!=', 'Archived')
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
            
            // Format for frontend
            $formattedBatches = $batches->map(function ($batch) {
                return [
                    'id' => (string) $batch->id,
                    'label' => $batch->name,
                    'course' => $batch->course ? $batch->course->name : 'General',
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedBatches,
                'message' => 'Batches retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get trainee statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $trainees = Trainee::all();
            
            $active = $trainees->where('enrollment_status', 'Enrolled')->count();
            $fieldAssignment = $trainees->where('enrollment_status', 'Enrolled')->count(); // You can add a field_assignment column if needed
            $pending = $trainees->where('enrollment_status', 'Pending')->count();
            $completed = $trainees->where('enrollment_status', 'Withdrawn')->count();
            
            // Count unique service types
            $serviceCount = $trainees->groupBy('service_type')->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'active' => $active,
                    'fieldAssignment' => $fieldAssignment,
                    'pending' => $pending,
                    'completed' => $completed,
                    'batchCount' => $serviceCount,
                    'total' => $trainees->count(),
                ],
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}