<?php
// app/Http/Controllers/Api/FacultyController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FacultyProfile;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class FacultyController extends Controller
{
    /**
     * Get all faculty members
     */
    public function index(): JsonResponse
    {
        try {
            $faculty = User::where('role', User::ROLE_FACULTY)
                ->with('facultyProfile')
                ->orderBy('name')
                ->get();
            
            // Transform data for frontend, handling missing profiles
            $facultyData = $faculty->map(function ($user) {
                // Check if profile exists, if not return default values
                $profile = $user->facultyProfile;
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'designation' => $profile ? $profile->designation : 'Faculty',
                    'speciality' => $profile ? $profile->speciality : '',
                    'station' => $profile ? $profile->station : '',
                    'department' => $profile ? $profile->department : 'General',
                    'phone' => $profile ? $profile->phone : '',
                    'status' => $profile ? $profile->status : 'Active',
                    'assigned_courses' => $profile && $profile->assigned_courses ? $profile->assigned_courses : [],
                    'assignedCourses' => $profile && $profile->assigned_courses ? count($profile->assigned_courses) : 0,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $facultyData,
                'message' => 'Faculty retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve faculty',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single faculty member
     */
    public function show($id): JsonResponse
    {
        try {
            $user = User::where('role', User::ROLE_FACULTY)
                ->with('facultyProfile')
                ->findOrFail($id);
            
            $profile = $user->facultyProfile;
            
            $facultyData = [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'designation' => $profile ? $profile->designation : 'Faculty',
                'speciality' => $profile ? $profile->speciality : '',
                'station' => $profile ? $profile->station : '',
                'department' => $profile ? $profile->department : 'General',
                'phone' => $profile ? $profile->phone : '',
                'status' => $profile ? $profile->status : 'Active',
                'assigned_courses' => $profile && $profile->assigned_courses ? $profile->assigned_courses : [],
                'assignedCourses' => $profile && $profile->assigned_courses ? count($profile->assigned_courses) : 0,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $facultyData,
                'message' => 'Faculty retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faculty not found'
            ], 404);
        }
    }
    
    /**
     * Create a new faculty member
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'designation' => 'required|string|max:255',
            'speciality' => 'required|string|max:255',
            'station' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'assigned_courses' => 'nullable|array',
            'assigned_courses.*' => 'exists:courses,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        try {
            // Create user account
            $user = User::create([
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_FACULTY,
                'email_verified_at' => now(),
            ]);
            
            // Create faculty profile
            FacultyProfile::create([
                'user_id' => $user->id,
                'designation' => $request->designation,
                'speciality' => $request->speciality,
                'station' => $request->station,
                'department' => $request->department,
                'phone' => $request->phone,
                'assigned_courses' => $request->assigned_courses ?? [],
                'status' => 'Active',
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'designation' => $request->designation,
                    'speciality' => $request->speciality,
                    'station' => $request->station,
                    'department' => $request->department,
                    'phone' => $request->phone,
                    'status' => 'Active',
                    'assigned_courses' => $request->assigned_courses ?? [],
                    'assignedCourses' => count($request->assigned_courses ?? []),
                ],
                'message' => 'Faculty created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create faculty',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update faculty member
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = User::where('role', User::ROLE_FACULTY)->findOrFail($id);
            $profile = $user->facultyProfile;
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:50|unique:users,username,' . $id,
                'email' => 'required|email|unique:users,email,' . $id,
                'password' => 'nullable|string|min:6',
                'designation' => 'required|string|max:255',
                'speciality' => 'required|string|max:255',
                'station' => 'required|string|max:255',
                'department' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'status' => ['required', Rule::in(['Active', 'Visiting', 'On Leave', 'Pending Review'])],
                'assigned_courses' => 'nullable|array',
                'assigned_courses.*' => 'exists:courses,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Update user
            $userData = [
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
            ];
            
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }
            
            $user->update($userData);
            
            // Update or create faculty profile
            if ($profile) {
                $profile->update([
                    'designation' => $request->designation,
                    'speciality' => $request->speciality,
                    'station' => $request->station,
                    'department' => $request->department,
                    'phone' => $request->phone,
                    'status' => $request->status,
                    'assigned_courses' => $request->assigned_courses ?? [],
                ]);
            } else {
                FacultyProfile::create([
                    'user_id' => $user->id,
                    'designation' => $request->designation,
                    'speciality' => $request->speciality,
                    'station' => $request->station,
                    'department' => $request->department,
                    'phone' => $request->phone,
                    'assigned_courses' => $request->assigned_courses ?? [],
                    'status' => $request->status,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'designation' => $request->designation,
                    'speciality' => $request->speciality,
                    'station' => $request->station,
                    'department' => $request->department,
                    'phone' => $request->phone,
                    'status' => $request->status,
                    'assigned_courses' => $request->assigned_courses ?? [],
                    'assignedCourses' => count($request->assigned_courses ?? []),
                ],
                'message' => 'Faculty updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update faculty',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete faculty member
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = User::where('role', User::ROLE_FACULTY)->findOrFail($id);
            
            // Soft delete user and profile will cascade
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Faculty deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete faculty',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all courses for dropdown
     */
    public function getCourses(): JsonResponse
    {
        try {
            $courses = Course::where('status', 'Published')
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
            
            return response()->json([
                'success' => true,
                'data' => $courses,
                'message' => 'Courses retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get faculty statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $faculty = User::where('role', User::ROLE_FACULTY)->with('facultyProfile')->get();
            
            $active = $faculty->filter(function ($user) {
                $profile = $user->facultyProfile;
                return $profile && $profile->status === 'Active';
            })->count();
            
            $visiting = $faculty->filter(function ($user) {
                $profile = $user->facultyProfile;
                return $profile && $profile->status === 'Visiting';
            })->count();
            
            $onLeave = $faculty->filter(function ($user) {
                $profile = $user->facultyProfile;
                return $profile && $profile->status === 'On Leave';
            })->count();
            
            $pending = $faculty->filter(function ($user) {
                $profile = $user->facultyProfile;
                return $profile && $profile->status === 'Pending Review';
            })->count();
            
            // Count users without profiles as active by default
            $withoutProfile = $faculty->filter(function ($user) {
                return !$user->facultyProfile;
            })->count();
            
            // Add those without profile to active count
            $active += $withoutProfile;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'active' => $active,
                    'visiting' => $visiting,
                    'onLeave' => $onLeave,
                    'pending' => $pending,
                    'total' => $faculty->count(),
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