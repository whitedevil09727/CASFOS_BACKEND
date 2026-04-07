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

class TraineeFeedbackController extends Controller
{
    /**
     * Get trainee's faculty assignments
     * GET /api/feedback/faculty/assignments
     */
    public function getFacultyAssignments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get trainee's batch
            $batch = $this->getUserBatch($user);
            $batchId = $batch?->id;
            $batchName = $batch?->name ?? 'N/A';
            
            // Get all unlocked faculty subjects
            $facultySubjects = DB::table('faculty_subjects as fs')
                ->leftJoin('faculty_profiles as fp', 'fs.faculty_id', '=', 'fp.id')
                ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
                ->leftJoin('courses as c', 'fs.course_id', '=', 'c.id')
                ->where('fs.feedback_unlocked', true)
                ->select(
                    'fs.id',
                    'fs.faculty_id',
                    'fs.course_id',
                    'fs.feedback_unlocked',
                    'fs.unlocked_at',
                    'fs.deadline_at',
                    'u.name as faculty_name',
                    'c.name as subject_name',
                    'c.code as subject_code'
                )
                ->get();
            
            $assignments = [];
            foreach ($facultySubjects as $fs) {
                // Check if trainee has already submitted feedback
                $existingResponse = FeedbackResponse::where('trainee_id', $user->id)
                    ->where('faculty_id', $fs->faculty_id)
                    ->where('course_id', $fs->course_id)
                    ->first();
                
                $status = $existingResponse ? 'submitted' : 'pending';
                
                // Check if deadline has passed
                if ($fs->deadline_at && now()->gt(Carbon::parse($fs->deadline_at)) && $status === 'pending') {
                    $status = 'expired';
                }
                
                $assignments[] = [
                    'id' => (string)$fs->id,
                    'trainee_id' => (string)$user->id,
                    'faculty_id' => (string)$fs->faculty_id,
                    'faculty_name' => $fs->faculty_name ?? 'Unknown Faculty',
                    'subject_id' => (string)$fs->course_id,
                    'subject_name' => $fs->subject_name ?? 'Unknown Course',
                    'batch_id' => $batchId ? (string)$batchId : '',
                    'batch_name' => $batchName,
                    'unlocked_by' => 'system',
                    'unlocked_at' => $fs->unlocked_at,
                    'deadline_at' => $fs->deadline_at,
                    'status' => $status,
                    'created_at' => now()->toISOString()
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $assignments
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getFacultyAssignments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit faculty feedback
     * POST /api/feedback/faculty/submit
     */
    public function submitFacultyFeedback(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_id' => 'required|string',
            'ratings' => 'required|array',
            'duration_rating' => 'required|in:appropriate,too_long,too_short',
            'poor_justification' => 'nullable|string'
        ]);
        
        try {
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
            
            $ratings = $request->ratings;
            
            // Map rating strings to numeric values
            $ratingValues = [
                'excellent' => 5,
                'very_good' => 4,
                'good' => 3,
                'fair' => 2,
                'poor' => 1
            ];
            
            $ratingMapping = [
                'content_relevance' => $ratingValues[$ratings['content_relevance'] ?? 'good'] ?? 3,
                'structure' => $ratingValues[$ratings['structure'] ?? 'good'] ?? 3,
                'clarity' => $ratingValues[$ratings['clarity'] ?? 'good'] ?? 3,
                'methodology' => $ratingValues[$ratings['methodology'] ?? 'good'] ?? 3,
                'vertical_learning' => $ratingValues[$ratings['vertical_learning'] ?? 'good'] ?? 3,
                'lateral_learning' => $ratingValues[$ratings['lateral_learning'] ?? 'good'] ?? 3,
            ];
            
            // Calculate overall rating
            $overallRating = round(array_sum($ratingMapping) / count($ratingMapping), 1);
            
            // Get batch ID
            $batch = $this->getUserBatch($user);
            $batchId = $batch?->id;
            
            $feedback = FeedbackResponse::updateOrCreate(
                [
                    'trainee_id' => $user->id,
                    'faculty_id' => $facultySubject->faculty_id,
                    'course_id' => $facultySubject->course_id,
                ],
                [
                    'batch_id' => $batchId,
                    'content_relevance' => $ratingMapping['content_relevance'],
                    'structure' => $ratingMapping['structure'],
                    'clarity' => $ratingMapping['clarity'],
                    'methodology' => $ratingMapping['methodology'],
                    'vertical_learning' => $ratingMapping['vertical_learning'],
                    'lateral_learning' => $ratingMapping['lateral_learning'],
                    'overall_rating' => $overallRating,
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
     * GET /api/feedback/final/assignment
     */
    public function getFinalAssignment(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get batch
            $batch = $this->getUserBatch($user);
            $batchId = $batch?->id;
            $batchName = $batch?->name ?? 'N/A';
            
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
            
            // Check if trainee has already submitted
            $existingResponse = FinalFeedback::where('trainee_id', $user->id)->first();
            $status = $existingResponse ? $existingResponse->status : 'pending';
            
            // Check if deadline has passed
            if ($releaseCycle->deadline_at && now()->gt(Carbon::parse($releaseCycle->deadline_at)) && $status === 'pending') {
                $status = 'expired';
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (string)$releaseCycle->id,
                    'trainee_id' => (string)$user->id,
                    'batch_id' => $batchId ? (string)$batchId : '',
                    'batch_name' => $batchName,
                    'released_by' => (string)$releaseCycle->released_by,
                    'released_at' => $releaseCycle->released_at,
                    'deadline_at' => $releaseCycle->deadline_at,
                    'status' => $status,
                    'created_at' => $releaseCycle->created_at
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getFinalAssignment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch final assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get trainee's final feedback response
     * GET /api/feedback/final/response
     */
    public function getFinalResponse(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $feedback = FinalFeedback::where('trainee_id', $user->id)->first();
            
            $responses = [];
            if ($feedback && $feedback->responses) {
                $responses = is_string($feedback->responses) 
                    ? json_decode($feedback->responses, true) 
                    : $feedback->responses;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $feedback->id ?? '',
                    'assignment_id' => $feedback->assignment_id ?? '',
                    'trainee_id' => (string)$user->id,
                    'responses' => $responses,
                    'status' => $feedback->status ?? 'draft',
                    'last_saved_at' => $feedback->updated_at ?? null,
                    'submitted_at' => $feedback->submitted_at ?? null
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getFinalResponse: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch final response',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Save final feedback section
     * POST /api/feedback/final/save-section
     */
    public function saveFinalSection(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_id' => 'required|string',
            'section_id' => 'required|string',
            'data' => 'required|array'
        ]);
        
        try {
            $user = $request->user();
            
            $feedback = FinalFeedback::firstOrNew(['trainee_id' => $user->id]);
            $feedback->assignment_id = $request->assignment_id;
            
            $responses = [];
            if ($feedback->responses) {
                $responses = is_string($feedback->responses) 
                    ? json_decode($feedback->responses, true) 
                    : $feedback->responses;
            }
            
            $responses[$request->section_id] = $request->data;
            
            $feedback->responses = json_encode($responses);
            $feedback->status = 'draft';
            $feedback->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Section saved successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in saveFinalSection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save section',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit final feedback
     * POST /api/feedback/final/submit
     */
    public function submitFinalFeedback(Request $request): JsonResponse
    {
        $request->validate([
            'assignment_id' => 'required|string'
        ]);
        
        try {
            $user = $request->user();
            
            $feedback = FinalFeedback::where('trainee_id', $user->id)->first();
            
            if (!$feedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'No feedback found to submit'
                ], 404);
            }
            
            $feedback->status = 'submitted';
            $feedback->submitted_at = now();
            $feedback->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Final feedback submitted successfully'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in submitFinalFeedback: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit final feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get faculty results (for faculty view)
     * GET /api/feedback/faculty/results
     */
    public function getFacultyResults(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $faculty = FacultyProfile::where('user_id', $user->id)->first();
            
            if (!$faculty) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $responses = FeedbackResponse::where('faculty_id', $faculty->id)
                ->where('status', 'submitted')
                ->with('course')
                ->get();
            
            $results = [];
            foreach ($responses->groupBy('course_id') as $courseId => $courseResponses) {
                $course = $courseResponses->first()->course;
                $totalResponses = $courseResponses->count();
                
                if ($totalResponses === 0) {
                    continue;
                }
                
                $results[] = [
                    'faculty_name' => $user->name,
                    'subject_name' => $course->name,
                    'response_rate' => [
                        'submitted' => $totalResponses,
                        'total' => 0
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
            
        } catch (\Exception $e) {
            \Log::error('Error in getFacultyResults: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch faculty results',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper method to get user's batch
     */
    private function getUserBatch($user)
    {
        // Check if user has batch_id column
        if ($user->batch_id) {
            return Batch::find($user->batch_id);
        }
        
        // Check many-to-many relationship
        if ($user->batches && $user->batches()->exists()) {
            return $user->batches()->first();
        }
        
        return null;
    }
}