<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourSubmission;
use App\Models\TourLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TourSubmissionController extends Controller
{
    // Remove the __construct method with middleware

    /**
     * Get all submissions (with filters)
     */
    public function index(Request $request)
    {
        // Course Clerk can see all submissions
        if (Auth::user()->role === 'course_clerk' || Auth::user()->role === 'admin') {
            $query = TourSubmission::with(['tourLink', 'trainee']);
        } 
        // Trainee can only see their own submissions
        else {
            $query = TourSubmission::where('trainee_id', Auth::id())->with('tourLink');
        }

        // Filter by tour
        if ($request->has('tour_name')) {
            $query->where('tour_name', $request->tour_name);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('trainee_name', 'like', "%{$search}%")
                  ->orWhere('roll_no', 'like', "%{$search}%");
            });
        }

        $submissions = $query->orderBy('submitted_at', 'desc')->paginate(20);

        // Get unique tour names for filter dropdown
        $uniqueTours = TourSubmission::distinct('tour_name')->pluck('tour_name');

        return response()->json([
            'success' => true,
            'data' => $submissions,
            'filters' => [
                'tours' => $uniqueTours
            ]
        ]);
    }

    /**
     * Get single submission details
     */
    public function show($id)
    {
        $submission = TourSubmission::with(['tourLink', 'trainee', 'reviewer'])
                                    ->findOrFail($id);

        // Check permission
        if (Auth::user()->role !== 'course_clerk' && 
            Auth::user()->role !== 'admin' && 
            $submission->trainee_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $submission
        ]);
    }

    /**
     * Mark submission as stored (after uploading to Google Drive)
     */
    public function markAsStored(Request $request, $id)
    {
        $submission = TourSubmission::findOrFail($id);

        // Only Course Clerk or Admin can mark as stored
        if (Auth::user()->role !== 'course_clerk' && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'google_drive_file_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $submission->markAsStored($request->google_drive_file_id);

        return response()->json([
            'success' => true,
            'message' => 'Journal marked as stored successfully',
            'data' => $submission
        ]);
    }

    /**
     * Approve submission
     */
    public function approve(Request $request, $id)
    {
        $submission = TourSubmission::findOrFail($id);

        // Only Course Clerk or Admin can approve
        if (Auth::user()->role !== 'course_clerk' && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $submission->approve($request->remarks, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Journal approved successfully',
            'data' => $submission
        ]);
    }

    /**
     * Reject submission
     */
    public function reject(Request $request, $id)
    {
        $submission = TourSubmission::findOrFail($id);

        // Only Course Clerk or Admin can reject
        if (Auth::user()->role !== 'course_clerk' && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $submission->reject($request->remarks, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Journal rejected',
            'data' => $submission
        ]);
    }

    /**
     * Get submission statistics for dashboard
     */
    public function statistics()
    {
        if (Auth::user()->role === 'course_clerk' || Auth::user()->role === 'admin') {
            $stats = [
                'total_submissions' => TourSubmission::count(),
                'pending' => TourSubmission::where('status', 'pending')->count(),
                'stored' => TourSubmission::where('status', 'stored')->count(),
                'approved' => TourSubmission::where('status', 'approved')->count(),
                'rejected' => TourSubmission::where('status', 'rejected')->count(),
                'active_links' => TourLink::active()->count(),
                'expired_links' => TourLink::expired()->count(),
            ];
        } else {
            // Trainee statistics
            $stats = [
                'total_submissions' => TourSubmission::where('trainee_id', Auth::id())->count(),
                'pending' => TourSubmission::where('trainee_id', Auth::id())->where('status', 'pending')->count(),
                'approved' => TourSubmission::where('trainee_id', Auth::id())->where('status', 'approved')->count(),
                'rejected' => TourSubmission::where('trainee_id', Auth::id())->where('status', 'rejected')->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Download submission (generate download URL)
     */
    public function download($id)
    {
        $submission = TourSubmission::findOrFail($id);

        // Check permission
        if (Auth::user()->role !== 'course_clerk' && 
            Auth::user()->role !== 'admin' && 
            $submission->trainee_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$submission->file_url && !$submission->google_drive_file_id) {
            return response()->json([
                'success' => false,
                'message' => 'No file attached to this submission'
            ], 404);
        }

        // Return the file URL or generate a download link
        return response()->json([
            'success' => true,
            'download_url' => $submission->file_url ?? $submission->google_drive_file_id
        ]);
    }
}