<?php
// app/Http/Controllers/Api/Admin/AppointmentController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Get list of faculty members eligible for appointment as Course Director
     */
    public function getEligibleFaculty(Request $request)
    {
        $faculty = User::where('role', User::ROLE_FACULTY)
            ->select('id', 'name', 'email', 'username', 'created_at')
            ->orderBy('name')
            ->get();

        // Get current director info
        $currentDirector = User::getCurrentCourseDirector();

        return response()->json([
            'success' => true,
            'data' => [
                'faculty' => $faculty,
                'current_director' => $currentDirector ? [
                    'id' => $currentDirector->id,
                    'name' => $currentDirector->name,
                    'email' => $currentDirector->email,
                    'appointed_at' => $currentDirector->appointed_at,
                    'appointed_by' => $currentDirector->appointedBy ? $currentDirector->appointedBy->name : null,
                    'term_start' => $currentDirector->term_start,
                    'term_end' => $currentDirector->term_end,
                ] : null,
            ]
        ]);
    }

    /**
     * Appoint a faculty member as Course Director
     * This will demote any existing Course Director first
     */
    public function appoint(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'term_start' => 'nullable|date',
            'term_end' => 'nullable|date|after:term_start',
            'remarks' => 'nullable|string',
        ]);

        $newDirector = User::findOrFail($request->user_id);

        // Check if user is faculty
        if (!$newDirector->isFaculty()) {
            throw ValidationException::withMessages([
                'user_id' => ['Only faculty members can be appointed as Course Director.']
            ]);
        }

        // Get the admin performing the appointment
        $admin = $request->user();

        DB::beginTransaction();
        try {
            // Get current Course Director if exists
            $currentDirector = User::getCurrentCourseDirector();

            // If there's a current director, demote them
            if ($currentDirector) {
                $currentDirector->update([
                    'role' => User::ROLE_FACULTY,
                    'is_current_director' => false,
                    'appointed_by' => null,
                    'appointed_at' => null,
                    'term_start' => null,
                    'term_end' => null,
                ]);
            }

            // Appoint new Course Director
            $newDirector->update([
                'role' => User::ROLE_COURSE_DIRECTOR,
                'appointed_by' => $admin->id,
                'appointed_at' => now(),
                'term_start' => $request->term_start ?? now(),
                'term_end' => $request->term_end,
                'is_current_director' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $currentDirector 
                    ? "{$newDirector->name} has been appointed as Course Director. {$currentDirector->name} has been demoted to Faculty."
                    : "{$newDirector->name} has been appointed as Course Director.",
                'data' => [
                    'current_director' => [
                        'id' => $newDirector->id,
                        'name' => $newDirector->name,
                        'email' => $newDirector->email,
                        'appointed_at' => $newDirector->appointed_at,
                        'appointed_by' => $admin->name,
                        'term_start' => $newDirector->term_start,
                        'term_end' => $newDirector->term_end,
                    ],
                    'previous_director' => $currentDirector ? [
                        'id' => $currentDirector->id,
                        'name' => $currentDirector->name,
                        'email' => $currentDirector->email,
                    ] : null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to appoint Course Director. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointment history (all past Course Directors)
     */
    public function getAppointmentHistory(Request $request)
    {
        $directors = User::where('role', User::ROLE_COURSE_DIRECTOR)
            ->orWhere(function($query) {
                $query->where('previous_role', User::ROLE_COURSE_DIRECTOR)
                    ->orWhereNotNull('appointed_at');
            })
            ->with('appointedBy:id,name,email')
            ->orderBy('appointed_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $directors->map(function ($director) {
                return [
                    'id' => $director->id,
                    'name' => $director->name,
                    'email' => $director->email,
                    'is_current' => $director->is_current_director,
                    'appointed_at' => $director->appointed_at,
                    'appointed_by' => $director->appointedBy ? $director->appointedBy->name : null,
                    'term_start' => $director->term_start,
                    'term_end' => $director->term_end,
                ];
            })
        ]);
    }

    /**
     * Get current Course Director details
     */
    public function getCurrentDirector(Request $request)
    {
        $currentDirector = User::getCurrentCourseDirector();

        if (!$currentDirector) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No Course Director currently appointed.'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $currentDirector->id,
                'name' => $currentDirector->name,
                'email' => $currentDirector->email,
                'username' => $currentDirector->username,
                'appointed_at' => $currentDirector->appointed_at,
                'appointed_by' => $currentDirector->appointedBy ? $currentDirector->appointedBy->name : null,
                'term_start' => $currentDirector->term_start,
                'term_end' => $currentDirector->term_end,
            ]
        ]);
    }

    /**
     * Get appointment statistics
     */
    public function getAppointmentStats(Request $request)
    {
        $stats = [
            'total_faculty' => User::where('role', User::ROLE_FACULTY)->count(),
            'current_director' => User::getCurrentCourseDirector() ? [
                'name' => User::getCurrentCourseDirector()->name,
                'appointed_at' => User::getCurrentCourseDirector()->appointed_at,
            ] : null,
            'total_past_directors' => User::whereNotNull('appointed_at')
                ->where('role', '!=', User::ROLE_COURSE_DIRECTOR)
                ->orWhere('is_current_director', false)
                ->count(),
            'appointment_history' => User::whereNotNull('appointed_at')
                ->orderBy('appointed_at', 'desc')
                ->take(10)
                ->with('appointedBy:id,name')
                ->get()
                ->map(function ($user) {
                    return [
                        'name' => $user->name,
                        'email' => $user->email,
                        'appointed_at' => $user->appointed_at,
                        'appointed_by' => $user->appointedBy ? $user->appointedBy->name : null,
                        'is_current' => $user->is_current_director,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Extend term for current Course Director
     */
    public function extendTerm(Request $request)
    {
        $request->validate([
            'term_end' => 'required|date|after:today',
        ]);

        $currentDirector = User::getCurrentCourseDirector();

        if (!$currentDirector) {
            return response()->json([
                'success' => false,
                'message' => 'No Course Director currently appointed.'
            ], 404);
        }

        $currentDirector->update([
            'term_end' => $request->term_end,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Term extended for {$currentDirector->name} until " . Carbon::parse($request->term_end)->format('d M Y'),
            'data' => [
                'name' => $currentDirector->name,
                'term_end' => $currentDirector->term_end,
            ]
        ]);
    }
}