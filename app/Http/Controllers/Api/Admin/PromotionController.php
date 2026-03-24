<?php
// app/Http/Controllers/Api/Admin/PromotionController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    /**
     * Get list of faculty members eligible for promotion
     */
    public function getEligibleFaculty(Request $request)
    {
        $faculty = User::where('role', User::ROLE_FACULTY)
            ->select('id', 'name', 'email', 'username', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $faculty
        ]);
    }

    /**
     * Promote a faculty member to Course Director
     */
    public function promote(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if user is faculty
        if (!$user->isFaculty()) {
            throw ValidationException::withMessages([
                'user_id' => ['Only faculty members can be promoted to Course Director.']
            ]);
        }

        // Check if user is already a course director
        if ($user->isCourseDirector()) {
            throw ValidationException::withMessages([
                'user_id' => ['User is already a Course Director.']
            ]);
        }

        // Get the admin performing the promotion
        $admin = $request->user();

        DB::beginTransaction();
        try {
            $user->update([
                'role' => User::ROLE_COURSE_DIRECTOR,
                'promoted_by' => $admin->id,
                'promoted_at' => now(),
                'previous_role' => User::ROLE_FACULTY,
            ]);

            // Optional: Create a promotion log/notification
            // You can add a notifications table to track this

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$user->name} has been successfully promoted to Course Director.",
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'previous_role' => $user->previous_role,
                        'promoted_at' => $user->promoted_at,
                        'promoted_by' => $admin->name,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to promote user. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Demote a Course Director back to Faculty
     */
    public function demote(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        // Check if user is a course director
        if (!$user->isCourseDirector()) {
            throw ValidationException::withMessages([
                'user_id' => ['Only Course Directors can be demoted.']
            ]);
        }

        DB::beginTransaction();
        try {
            // Demote back to faculty
            $user->update([
                'role' => User::ROLE_FACULTY,
                'promoted_by' => null,
                'promoted_at' => null,
                'previous_role' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$user->name} has been demoted to Faculty.",
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to demote user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of current Course Directors (for management)
     */
    public function getCourseDirectors(Request $request)
    {
        $directors = User::where('role', User::ROLE_COURSE_DIRECTOR)
            ->with('promotedBy:id,name,email')
            ->select('id', 'name', 'email', 'username', 'promoted_by', 'promoted_at', 'previous_role', 'created_at')
            ->orderBy('promoted_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $directors->map(function ($director) {
                return [
                    'id' => $director->id,
                    'name' => $director->name,
                    'email' => $director->email,
                    'username' => $director->username,
                    'promoted_at' => $director->promoted_at,
                    'promoted_by' => $director->promotedBy ? $director->promotedBy->name : null,
                    'promoted_by_email' => $director->promotedBy ? $director->promotedBy->email : null,
                ];
            })
        ]);
    }

    /**
     * Get promotion history/statistics
     */
    public function getPromotionStats(Request $request)
    {
        $stats = [
            'total_faculty' => User::where('role', User::ROLE_FACULTY)->count(),
            'total_course_directors' => User::where('role', User::ROLE_COURSE_DIRECTOR)->count(),
            'recent_promotions' => User::whereNotNull('promoted_at')
                ->orderBy('promoted_at', 'desc')
                ->take(5)
                ->with('promotedBy:id,name')
                ->get()
                ->map(function ($user) {
                    return [
                        'name' => $user->name,
                        'email' => $user->email,
                        'promoted_at' => $user->promoted_at,
                        'promoted_by' => $user->promotedBy ? $user->promotedBy->name : null,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}