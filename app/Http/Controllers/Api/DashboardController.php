<?php
// app/Http/Controllers/Api/DashboardController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard data based on user role
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Return different data based on user role
        switch ($user->role) {
            case 'admin':
                return $this->adminDashboard($user);
            case 'faculty':
                return $this->facultyDashboard($user);
            case 'trainee':
                return $this->traineeDashboard($user);
            default:
                return response()->json(['message' => 'Invalid role'], 403);
        }
    }

    /**
     * Admin specific dashboard data
     */
    private function adminDashboard($user)
    {
        // Fetch admin-specific data
        $data = [
            'total_users' => \App\Models\User::count(),
            'total_courses' => \App\Models\Course::count(),
            'recent_activities' => \App\Models\ActivityLog::latest()->take(10)->get(),
            'system_stats' => [
                'active_sessions' => 45,
                'pending_approvals' => 12,
            ]
        ];

        return response()->json([
            'success' => true,
            'role' => 'admin',
            'dashboard_data' => $data,
            'message' => 'Welcome to Admin Dashboard'
        ]);
    }

    /**
     * Faculty specific dashboard data
     */
    private function facultyDashboard($user)
    {
        // Fetch faculty-specific data
        $data = [
            'my_courses' => \App\Models\Course::where('faculty_id', $user->id)->get(),
            'pending_assignments' => \App\Models\Assignment::where('faculty_id', $user->id)
                                    ->where('status', 'pending')->count(),
            'upcoming_classes' => \App\Models\Schedule::where('faculty_id', $user->id)
                                 ->where('date', '>=', now())->get(),
        ];

        return response()->json([
            'success' => true,
            'role' => 'faculty',
            'dashboard_data' => $data,
            'message' => 'Welcome to Faculty Dashboard'
        ]);
    }

    /**
     * Trainee specific dashboard data
     */
    private function traineeDashboard($user)
    {
        // Fetch trainee-specific data
        $data = [
            'enrolled_courses' => \App\Models\Enrollment::where('user_id', $user->id)
                                  ->with('course')->get(),
            'completed_assignments' => \App\Models\Submission::where('user_id', $user->id)
                                      ->where('status', 'completed')->count(),
            'upcoming_sessions' => \App\Models\Schedule::whereHas('enrollment', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('date', '>=', now())->get(),
            'progress' => 75, // Example progress percentage
        ];

        return response()->json([
            'success' => true,
            'role' => 'trainee',
            'dashboard_data' => $data,
            'message' => 'Welcome to Trainee Dashboard'
        ]);
    }

    /**
     * Get specific admin functions
     */
    public function adminUsers(Request $request)
    {
        // Check if user is admin
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = \App\Models\User::paginate(20);
        return response()->json(['success' => true, 'users' => $users]);
    }

    /**
     * Get specific faculty functions
     */
    public function facultyCourses(Request $request)
    {
        if ($request->user()->role !== 'faculty') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $courses = \App\Models\Course::where('faculty_id', $request->user()->id)->get();
        return response()->json(['success' => true, 'courses' => $courses]);
    }

    /**
     * Get specific trainee functions
     */
    public function traineeProgress(Request $request)
    {
        if ($request->user()->role !== 'trainee') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $progress = \App\Models\Progress::where('user_id', $request->user()->id)->get();
        return response()->json(['success' => true, 'progress' => $progress]);
    }
}