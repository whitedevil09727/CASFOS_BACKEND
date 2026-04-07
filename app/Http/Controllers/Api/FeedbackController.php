<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\FacultySubject;
use App\Models\FeedbackResponse;
use App\Models\FinalFeedback;
use App\Models\FeedbackReleaseCycle;
use App\Models\FacultyProfile;
use App\Models\Course;
use App\Models\User;
use App\Models\Batch;
use Carbon\Carbon;

class FeedbackController extends Controller
{
    /**
     * Get faculty subjects for admin view
     * Syncs with assigned_courses JSON field from faculty_profiles
     */

       public function getFacultySubjects(Request $request): JsonResponse
    {
        try {
            // Direct query using DB facade to ensure we get data
            $results = DB::table('faculty_subjects as fs')
                ->leftJoin('faculty_profiles as fp', 'fs.faculty_id', '=', 'fp.id')
                ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
                ->leftJoin('courses as c', 'fs.course_id', '=', 'c.id')
                ->select(
                    'fs.id',
                    'fs.faculty_id',
                    'fs.course_id',
                    'fs.syllabus_status',
                    'fs.feedback_unlocked',
                    'fs.unlocked_at',
                    'fs.deadline_at',
                    'fs.created_at',
                    'u.name as faculty_name',
                    'u.email as faculty_email',
                    'c.name as subject_name',
                    'c.code as subject_code',
                    'c.category as subject_category'
                )
                ->orderBy('fs.created_at', 'desc');
            
            // Apply search filter if provided
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $results->where(function($q) use ($search) {
                    $q->where('u.name', 'ilike', "%{$search}%")
                      ->orWhere('c.name', 'ilike', "%{$search}%")
                      ->orWhere('c.code', 'ilike', "%{$search}%");
                });
            }
            
            // Apply status filter
            if ($request->has('syllabus_status') && !empty($request->syllabus_status)) {
                $results->where('fs.syllabus_status', $request->syllabus_status);
            }
            
            // Apply unlocked filter
            if ($request->has('feedback_unlocked') && $request->feedback_unlocked !== '') {
                $results->where('fs.feedback_unlocked', $request->feedback_unlocked === 'true');
            }
            
            $facultySubjects = $results->get();
            
            // Transform data for frontend
            $data = [];
            foreach ($facultySubjects as $fs) {
                // Calculate stats for this faculty-course combination
                $totalResponses = FeedbackResponse::where('faculty_id', $fs->faculty_id)
                    ->where('course_id', $fs->course_id)
                    ->count();
                    
                $submittedResponses = FeedbackResponse::where('faculty_id', $fs->faculty_id)
                    ->where('course_id', $fs->course_id)
                    ->where('status', 'submitted')
                    ->count();
                    
                $pendingResponses = $totalResponses - $submittedResponses;
                
                $data[] = [
                    'faculty_id' => (string)$fs->faculty_id,
                    'faculty_name' => $fs->faculty_name ?? 'Unknown Faculty',
                    'subject_id' => (string)$fs->course_id,
                    'subject_name' => $fs->subject_name ?? 'Unknown Course',
                    'subject_code' => $fs->subject_code ?? 'N/A',
                    'syllabus_status' => $fs->syllabus_status ?? 'pending',
                    'feedback_unlocked' => (bool)$fs->feedback_unlocked,
                    'unlocked_at' => $fs->unlocked_at,
                    'deadline_at' => $fs->deadline_at,
                    'stats' => [
                        'total' => $totalResponses,
                        'submitted' => $submittedResponses,
                        'pending' => $pendingResponses,
                        'expired' => 0
                    ]
                ];
            }
            
            // Get summary statistics
            $summary = [
                'total_faculty' => FacultyProfile::count(),
                'total_courses' => Course::count(),
                'active_cycles' => FacultySubject::where('feedback_unlocked', true)->count(),
                'pending_unlock' => FacultySubject::where('feedback_unlocked', false)
                    ->where('syllabus_status', 'completed')
                    ->count(),
                'total_responses' => FeedbackResponse::where('status', 'submitted')->count(),
                'avg_rating' => round(FeedbackResponse::where('status', 'submitted')->avg('overall_rating') ?? 0, 1),
                'total_faculty_subjects' => FacultySubject::count() // Debug info
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getFacultySubjects: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch faculty subjects',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sync faculty courses from the assigned_courses JSON field in faculty_profiles
     * This is key for your JSON structure
     */
    // private function syncFacultyCoursesFromJson(): void
    // {
    //     $faculties = FacultyProfile::all();
        
    //     foreach ($faculties as $faculty) {
    //         // Parse the JSON assigned_courses field
    //         $assignedCourses = is_array($faculty->assigned_courses) 
    //             ? $faculty->assigned_courses 
    //             : json_decode($faculty->assigned_courses, true);
            
    //         if (!empty($assignedCourses) && is_array($assignedCourses)) {
    //             foreach ($assignedCourses as $courseId) {
    //                 // Create or update faculty_subject entry
    //                 FacultySubject::updateOrCreate(
    //                     [
    //                         'faculty_id' => $faculty->id,
    //                         'course_id' => $courseId
    //                     ],
    //                     [
    //                         'syllabus_status' => 'completed', // Default status
    //                         'feedback_unlocked' => false
    //                     ]
    //                 );
    //             }
    //         }
    //     }
    // }

  /**
 * Sync faculty courses from the assigned_courses JSON field in faculty_profiles
 * Call this method when needed to ensure data is synced
 */
private function syncFacultyCoursesFromJson(): void
{
    $faculties = FacultyProfile::all();
    $syncedCount = 0;
    
    foreach ($faculties as $faculty) {
        // Get assigned courses safely
        $assignedCourses = $faculty->assigned_courses;
        
        // Handle if it's a string (JSON)
        if (is_string($assignedCourses)) {
            $assignedCourses = json_decode($assignedCourses, true);
        }
        
        // Ensure it's an array
        if (!is_array($assignedCourses)) {
            $assignedCourses = [];
        }
        
        // Get current mappings for this faculty
        $currentMappings = FacultySubject::where('faculty_id', $faculty->id)
            ->pluck('course_id')
            ->toArray();
        
        // Add new mappings
        foreach ($assignedCourses as $courseId) {
            // Verify course exists
            if (Course::find($courseId)) {
                FacultySubject::updateOrCreate(
                    [
                        'faculty_id' => $faculty->id,
                        'course_id' => $courseId
                    ],
                    [
                        'syllabus_status' => 'completed',
                        'feedback_unlocked' => false
                    ]
                );
                $syncedCount++;
            }
        }
        
        // Optional: Remove mappings that are no longer in assigned_courses
        $toRemove = array_diff($currentMappings, $assignedCourses);
        if (!empty($toRemove)) {
            FacultySubject::where('faculty_id', $faculty->id)
                ->whereIn('course_id', $toRemove)
                ->delete();
        }
    }
    
    \Log::info("Synced {$syncedCount} faculty-subject mappings");
}
    
    /**
     * Unlock feedback for a faculty course
     */
    public function unlockFeedback(Request $request): JsonResponse
    {
        $request->validate([
            'faculty_id' => 'required|exists:faculty_profiles,id',
            'course_id' => 'required|exists:courses,id',
            'deadline_days' => 'nullable|integer|min:1|max:30'
        ]);
        
        $facultySubject = FacultySubject::where('faculty_id', $request->faculty_id)
            ->where('course_id', $request->course_id)
            ->firstOrFail();
        
        // Check if syllabus is completed
        if ($facultySubject->syllabus_status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unlock feedback until syllabus is completed'
            ], 422);
        }
        
        $deadlineDays = $request->deadline_days ?? 7;
        
        DB::beginTransaction();
        try {
            $facultySubject->update([
                'feedback_unlocked' => true,
                'unlocked_at' => now(),
                'deadline_at' => now()->addDays($deadlineDays)
            ]);
            
            // Create feedback release cycle record
            FeedbackReleaseCycle::create([
                'type' => 'faculty',
                'is_active' => true,
                'released_at' => now(),
                'deadline_at' => now()->addDays($deadlineDays),
                'released_by' => auth()->id()
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Feedback unlocked successfully',
                'data' => $facultySubject->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to unlock feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get faculty feedback analytics
     */
    public function getFacultyAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'faculty_id' => 'required|exists:faculty_profiles,id',
            'course_id' => 'required|exists:courses,id'
        ]);
        
        $responses = FeedbackResponse::where('faculty_id', $request->faculty_id)
            ->where('course_id', $request->course_id)
            ->where('status', 'submitted')
            ->get();
        
        $totalResponses = $responses->count();
        
        if ($totalResponses === 0) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No responses yet'
            ]);
        }
        
        // Calculate averages for each criterion
        $criteriaAverages = [
            'content_relevance' => round($responses->avg('content_relevance'), 1),
            'structure' => round($responses->avg('structure'), 1),
            'clarity' => round($responses->avg('clarity'), 1),
            'methodology' => round($responses->avg('methodology'), 1),
            'vertical_learning' => round($responses->avg('vertical_learning'), 1),
            'lateral_learning' => round($responses->avg('lateral_learning'), 1),
        ];
        
        // Duration breakdown
        $durationBreakdown = [
            'appropriate' => $responses->where('duration_rating', 'appropriate')->count(),
            'too_long' => $responses->where('duration_rating', 'too_long')->count(),
            'too_short' => $responses->where('duration_rating', 'too_short')->count(),
        ];
        
        // Get qualitative feedback
        $poorJustifications = $responses->whereNotNull('improvement_suggestions')
            ->take(5)
            ->pluck('improvement_suggestions')
            ->toArray();
        
        if (empty($poorJustifications)) {
            $poorJustifications = ["No specific feedback provided yet."];
        }
        
        // Criteria distribution for charts
        $criteriaDistribution = [];
        $criteriaFields = ['content_relevance', 'structure', 'clarity', 'methodology', 'vertical_learning', 'lateral_learning'];
        
        foreach ($criteriaFields as $criteria) {
            $distribution = [
                1 => $responses->where($criteria, 1)->count(),
                2 => $responses->where($criteria, 2)->count(),
                3 => $responses->where($criteria, 3)->count(),
                4 => $responses->where($criteria, 4)->count(),
                5 => $responses->where($criteria, 5)->count(),
            ];
            $criteriaDistribution[$criteria] = $distribution;
        }
        
        $faculty = FacultyProfile::with('user')->find($request->faculty_id);
        $course = Course::find($request->course_id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'faculty_name' => $faculty->user->name ?? 'Unknown Faculty',
                'subject_name' => $course->name,
                'subject_code' => $course->code,
                'response_rate' => [
                    'submitted' => $totalResponses,
                    'total' => $this->getTotalExpectedResponses($request->faculty_id, $request->course_id)
                ],
                'criteria_averages' => $criteriaAverages,
                'duration_breakdown' => $durationBreakdown,
                'poor_justifications' => $poorJustifications,
                'criteria_distribution' => $criteriaDistribution,
                'overall_rating' => round($responses->avg('overall_rating'), 1)
            ]
        ]);
    }
    
    /**
     * Get total expected responses for a faculty-course combination
     */
    private function getTotalExpectedResponses($facultyId, $courseId): int
    {
        // Get all trainees who are enrolled in this course
        // Adjust this based on your trainee-course enrollment logic
        return User::where('role', 'trainee')
            ->whereHas('batches', function($q) use ($courseId) {
                $q->whereHas('courses', function($sub) use ($courseId) {
                    $sub->where('course_id', $courseId);
                });
            })->count();
    }
    
    /**
     * Get final feedback monitoring
     */
    public function getFinalMonitoring(Request $request): JsonResponse
    {
        $batchId = $request->batch_id;
        $courseId = $request->course_id;
        
        $query = User::where('role', 'trainee');
        
        if ($batchId) {
            $query->whereHas('batches', function($q) use ($batchId) {
                $q->where('batch_id', $batchId);
            });
        }
        
        $trainees = $query->get();
        
        $monitoring = $trainees->map(function($trainee) use ($courseId) {
            $feedback = FinalFeedback::where('trainee_id', $trainee->id)
                ->when($courseId, function($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                })
                ->first();
            
            // Count completed sections (8 sections total)
            $sectionsCompleted = 0;
            if ($feedback) {
                $sections = [
                    $feedback->overall_rating,
                    $feedback->course_content,
                    $feedback->teaching_quality,
                    $feedback->infrastructure,
                    $feedback->placement_support,
                    $feedback->strengths,
                    $feedback->areas_for_improvement,
                    $feedback->recommendations
                ];
                $sectionsCompleted = collect($sections)->filter(function($item) {
                    return !is_null($item) && $item !== '';
                })->count();
            }
            
            return [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'roll_no' => $trainee->roll_no ?? 'N/A',
                'status' => $feedback ? $feedback->status : 'draft',
                'sections' => $sectionsCompleted,
                'last_action' => $feedback ? $feedback->updated_at->diffForHumans() : 'No activity'
            ];
        });
        
        // Check if final feedback is released
        $isReleased = FeedbackReleaseCycle::where('type', 'final')
            ->where('is_active', true)
            ->exists();
        
        return response()->json([
            'success' => true,
            'data' => $monitoring,
            'is_released' => $isReleased
        ]);
    }
    
    /**
     * Release final feedback cycle
     */
    public function releaseFinalFeedback(Request $request): JsonResponse
    {
        $request->validate([
            'deadline_days' => 'nullable|integer|min:1|max:30'
        ]);
        
        // Deactivate previous cycles
        FeedbackReleaseCycle::where('type', 'final')->update(['is_active' => false]);
        
        $deadlineDays = $request->deadline_days ?? 14;
        
        $cycle = FeedbackReleaseCycle::create([
            'type' => 'final',
            'is_active' => true,
            'released_at' => now(),
            'deadline_at' => now()->addDays($deadlineDays),
            'released_by' => auth()->id()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Final feedback cycle released successfully',
            'data' => $cycle
        ]);
    }
    
    /**
     * Get feedback summary statistics
     */
    public function getFeedbackSummary(): JsonResponse
    {
        $summary = [
            'total_responses' => FeedbackResponse::where('status', 'submitted')->count(),
            'response_rate' => $this->calculateResponseRate(),
            'active_cycles' => FacultySubject::where('feedback_unlocked', true)->count(),
            'faculty_count' => FacultyProfile::count(),
            'avg_rating' => round(FeedbackResponse::where('status', 'submitted')->avg('overall_rating') ?? 0, 1),
            'pending_unlock' => FacultySubject::where('feedback_unlocked', false)
                ->where('syllabus_status', 'completed')
                ->count(),
            'submitted_this_week' => FeedbackResponse::where('status', 'submitted')
                ->where('submitted_at', '>=', now()->subDays(7))
                ->count()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
    
    private function calculateResponseRate(): int
    {
        $totalExpected = User::where('role', 'trainee')->count() * FacultySubject::count();
        $totalReceived = FeedbackResponse::where('status', 'submitted')->count();
        
        if ($totalExpected === 0) return 0;
        
        return round(($totalReceived / $totalExpected) * 100);
    }
    
    /**
     * Update syllabus status for a faculty course
     */
    public function updateSyllabusStatus(Request $request): JsonResponse
    {
        $request->validate([
            'faculty_id' => 'required|exists:faculty_profiles,id',
            'course_id' => 'required|exists:courses,id',
            'status' => 'required|in:pending,in_progress,completed'
        ]);
        
        $facultySubject = FacultySubject::where('faculty_id', $request->faculty_id)
            ->where('course_id', $request->course_id)
            ->firstOrFail();
        
        $facultySubject->update(['syllabus_status' => $request->status]);
        
        return response()->json([
            'success' => true,
            'message' => 'Syllabus status updated successfully',
            'data' => $facultySubject
        ]);
    }

     /**
     * Get feedback summary statistics
     */
    // public function getFeedbackSummary(): JsonResponse
    // {
    //     try {
    //         // Get total faculty count
    //         $facultyCount = FacultyProfile::count();
            
    //         // Get active feedback cycles (unlocked faculty subjects)
    //         $activeCycles = FacultySubject::where('feedback_unlocked', true)->count();
            
    //         // Get pending unlock (completed syllabus but not unlocked)
    //         $pendingUnlock = FacultySubject::where('feedback_unlocked', false)
    //             ->where('syllabus_status', 'completed')
    //             ->count();
            
    //         // Get total responses
    //         $totalResponses = FeedbackResponse::where('status', 'submitted')->count();
            
    //         // Calculate average rating
    //         $avgRating = round(FeedbackResponse::where('status', 'submitted')->avg('overall_rating') ?? 0, 1);
            
    //         // Calculate response rate
    //         $totalExpected = $this->calculateTotalExpectedResponses();
    //         $responseRate = $totalExpected > 0 ? round(($totalResponses / $totalExpected) * 100) : 0;
            
    //         // Get responses submitted this week
    //         $submittedThisWeek = FeedbackResponse::where('status', 'submitted')
    //             ->where('submitted_at', '>=', now()->subDays(7))
    //             ->count();
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'total_responses' => $totalResponses,
    //                 'response_rate' => $responseRate,
    //                 'active_cycles' => $activeCycles,
    //                 'faculty_count' => $facultyCount,
    //                 'avg_rating' => $avgRating,
    //                 'pending_unlock' => $pendingUnlock,
    //                 'submitted_this_week' => $submittedThisWeek
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('Error getting feedback summary: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to fetch summary statistics',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Calculate total expected responses across all faculty subjects
     */
    private function calculateTotalExpectedResponses(): int
    {
        // Get all active faculty subjects
        $facultySubjects = FacultySubject::where('feedback_unlocked', true)->get();
        
        if ($facultySubjects->isEmpty()) {
            return 0;
        }
        
        // Get all trainees
        $traineeCount = User::where('role', 'trainee')->count();
        
        // Total expected = number of faculty subjects * number of trainees
        return $facultySubjects->count() * $traineeCount;
    }

    /**
     * Get trainee's faculty assignments
     */
    public function getTraineeFacultyAssignments(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get trainee's batch
        $trainee = User::with('batches')->find($user->id);
        $batchId = $trainee->batches->first()?->id ?? null;
        
        if (!$batchId) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
        
        // Get all unlocked faculty subjects
        $facultySubjects = FacultySubject::with(['faculty.user', 'course'])
            ->where('feedback_unlocked', true)
            ->get();
        
        $assignments = [];
        foreach ($facultySubjects as $fs) {
            // Check if trainee has already submitted feedback
            $existingResponse = FeedbackResponse::where('trainee_id', $user->id)
                ->where('faculty_id', $fs->faculty_id)
                ->where('course_id', $fs->course_id)
                ->first();
            
            $assignments[] = [
                'id' => $fs->id,
                'trainee_id' => $user->id,
                'faculty_id' => $fs->faculty_id,
                'faculty_name' => $fs->faculty->user->name ?? 'Unknown',
                'subject_id' => $fs->course_id,
                'subject_name' => $fs->course->name,
                'batch_id' => $batchId,
                'batch_name' => $trainee->batches->first()?->name ?? 'N/A',
                'unlocked_by' => 'system',
                'unlocked_at' => $fs->unlocked_at,
                'deadline_at' => $fs->deadline_at,
                'status' => $existingResponse ? 'submitted' : 'pending',
                'created_at' => $fs->created_at
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    /**
     * Submit faculty feedback
     */
    public function submitFacultyFeedback(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_id' => 'required|string',
            'ratings' => 'required|array',
            'duration_rating' => 'required|in:appropriate,too_long,too_short',
            'poor_justification' => 'nullable|string'
        ]);
        
        $user = $request->user();
        
        // Find faculty subject
        $facultySubject = FacultySubject::find($request->assignment_id);
        if (!$facultySubject) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found'
            ], 404);
        }
        
        // Check if already submitted
        $existing = FeedbackResponse::where('trainee_id', $user->id)
            ->where('faculty_id', $facultySubject->faculty_id)
            ->where('course_id', $facultySubject->course_id)
            ->first();
        
        if ($existing && $existing->status === 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Feedback already submitted'
            ], 400);
        }
        
        try {
            $ratings = $request->ratings;
            
            $feedback = FeedbackResponse::updateOrCreate(
                [
                    'trainee_id' => $user->id,
                    'faculty_id' => $facultySubject->faculty_id,
                    'course_id' => $facultySubject->course_id,
                ],
                [
                    'batch_id' => $user->batches->first()?->id,
                    'content_relevance' => $ratings['content_relevance'] ?? null,
                    'structure' => $ratings['structure'] ?? null,
                    'clarity' => $ratings['clarity'] ?? null,
                    'methodology' => $ratings['methodology'] ?? null,
                    'vertical_learning' => $ratings['vertical_learning'] ?? null,
                    'lateral_learning' => $ratings['lateral_learning'] ?? null,
                    'duration_rating' => $request->duration_rating,
                    'improvement_suggestions' => $request->poor_justification,
                    'status' => 'submitted',
                    'submitted_at' => now()
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'data' => $feedback
            ]);
        } catch (\Exception $e) {
            \Log::error('Error submitting faculty feedback: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trainee's final feedback assignment
     */
    public function getTraineeFinalAssignment(Request $request): JsonResponse
    {
        $user = $request->user();
        $batchId = $user->batches->first()?->id;
        
        // Check if final feedback is released
        $releaseCycle = FeedbackReleaseCycle::where('type', 'final')
            ->where('is_active', true)
            ->first();
        
        if (!$releaseCycle) {
            return response()->json([
                'success' => false,
                'message' => 'Final feedback not released yet'
            ], 404);
        }
        
        $existingResponse = FinalFeedback::where('trainee_id', $user->id)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $releaseCycle->id,
                'trainee_id' => $user->id,
                'batch_id' => $batchId,
                'batch_name' => $user->batches->first()?->name ?? 'N/A',
                'released_by' => $releaseCycle->released_by,
                'released_at' => $releaseCycle->released_at,
                'deadline_at' => $releaseCycle->deadline_at,
                'status' => $existingResponse ? $existingResponse->status : 'pending',
                'created_at' => $releaseCycle->created_at
            ]
        ]);
    }

    /**
     * Get trainee's final feedback response
     */
    public function getTraineeFinalResponse(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $feedback = FinalFeedback::where('trainee_id', $user->id)->first();
        
        // Parse responses if stored as JSON
        $responses = [];
        if ($feedback && $feedback->responses) {
            $responses = is_string($feedback->responses) 
                ? json_decode($feedback->responses, true) 
                : $feedback->responses;
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feedback->id ?? null,
                'assignment_id' => $feedback->assignment_id ?? null,
                'trainee_id' => $user->id,
                'responses' => $responses,
                'status' => $feedback->status ?? 'draft',
                'last_saved_at' => $feedback->updated_at ?? null,
                'submitted_at' => $feedback->submitted_at ?? null
            ]
        ]);
    }

    /**
     * Save final feedback section
     */
    public function saveFinalSection(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_id' => 'required',
            'section_id' => 'required|string',
            'data' => 'required|array'
        ]);
        
        $user = $request->user();
        
        $feedback = FinalFeedback::firstOrNew(['trainee_id' => $user->id]);
        
        // Get existing responses or initialize empty array
        $responses = [];
        if ($feedback->responses) {
            $responses = is_string($feedback->responses) 
                ? json_decode($feedback->responses, true) 
                : $feedback->responses;
        }
        
        // Update the specific section
        $responses[$request->section_id] = $request->data;
        
        $feedback->responses = $responses;
        $feedback->status = 'draft';
        $feedback->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Section saved successfully'
        ]);
    }

    /**
     * Submit final feedback
     */
    public function submitFinalFeedbackTrainee(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_id' => 'required'
        ]);
        
        $user = $request->user();
        
        $feedback = FinalFeedback::where('trainee_id', $user->id)->first();
        
        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'No feedback found'
            ], 404);
        }
        
        $feedback->status = 'submitted';
        $feedback->submitted_at = now();
        $feedback->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Final feedback submitted successfully'
        ]);
    }

    /**
     * Get faculty results (for faculty view)
     */
    public function getFacultyResults(Request $request): JsonResponse
    {

        $user = $request->user();
        
        // Get faculty profile
        $faculty = FacultyProfile::where('user_id', $user->id)->first();
        
        if (!$faculty) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
        
        // Get all feedback responses for this faculty
        $responses = FeedbackResponse::where('faculty_id', $faculty->id)
            ->where('status', 'submitted')
            ->with('course')
            ->get();
        
        // Group by course
        $results = [];
        foreach ($responses->groupBy('course_id') as $courseId => $courseResponses) {
            $course = $courseResponses->first()->course;
            
            $results[] = [
                'faculty_name' => $user->name,
                'subject_name' => $course->name,
                'response_rate' => [
                    'submitted' => $courseResponses->count(),
                    'total' => $this->getTotalExpectedResponses($faculty->id, $courseId)
                ],
                'criteria_averages' => [
                    'content_relevance' => round($courseResponses->avg('content_relevance'), 1),
                    'structure' => round($courseResponses->avg('structure'), 1),
                    'clarity' => round($courseResponses->avg('clarity'), 1),
                    'methodology' => round($courseResponses->avg('methodology'), 1),
                    'vertical_learning' => round($courseResponses->avg('vertical_learning'), 1),
                    'lateral_learning' => round($courseResponses->avg('lateral_learning'), 1),
                ],
                'duration_breakdown' => [
                    'appropriate' => $courseResponses->where('duration_rating', 'appropriate')->count(),
                    'too_long' => $courseResponses->where('duration_rating', 'too_long')->count(),
                    'too_short' => $courseResponses->where('duration_rating', 'too_short')->count(),
                ],
                'poor_justifications' => $courseResponses->pluck('improvement_suggestions')
                    ->filter()
                    ->values()
                    ->toArray()
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

}