<?php
// app/Http/Controllers/Api/DashboardController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Check if user has admin-level access (admin or course_director)
        if ($user->isAdminLevel()) {
            return $this->adminDashboard($user);
        } elseif ($user->isFaculty()) {
            return $this->facultyDashboard($user);
        } elseif ($user->isTrainee()) {
            return $this->traineeDashboard($user);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid role'
        ], 403);
    }

    private function adminDashboard($user)
    {
        $currentDirector = User::getCurrentCourseDirector();
        
        $data = [
            'stats' => [
                'total_users' => User::count(),
                'users_by_role' => [
                    'admin' => User::where('role', 'admin')->count(),
                    'course_director' => User::where('role', 'course_director')->count(),
                    'faculty' => User::where('role', 'faculty')->count(),
                    'trainee' => User::where('role', 'trainee')->count(),
                ],
            ],
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
                'role_display' => $user->role === 'course_director' ? 'Course Director' : 'Admin',
            ],
            'current_course_director' => $currentDirector ? [
                'name' => $currentDirector->name,
                'email' => $currentDirector->email,
                'appointed_at' => $currentDirector->appointed_at,
                'term_end' => $currentDirector->term_end,
            ] : null,
            'recent_users' => User::latest()
                ->take(5)
                ->get(['id', 'name', 'email', 'role', 'created_at']),
            'message' => 'Welcome to Admin Dashboard',
        ];

        return response()->json([
            'success' => true,
            'role' => $user->role,
            'dashboard_data' => $data
        ]);
    }

    private function facultyDashboard($user)
    {
        return response()->json([
            'success' => true,
            'role' => 'faculty',
            'dashboard_data' => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'message' => 'Welcome to Faculty Dashboard',
                'can_be_promoted' => true,
            ]
        ]);
    }

    private function traineeDashboard($user)
    {
        return response()->json([
            'success' => true,
            'role' => 'trainee',
            'dashboard_data' => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'message' => 'Welcome to Trainee Dashboard',
            ]
        ]);
    }
}