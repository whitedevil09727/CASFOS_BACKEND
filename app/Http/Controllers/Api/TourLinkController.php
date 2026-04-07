<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourLink;
use App\Models\TourBatch;
use App\Models\TourSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TourLinkController extends Controller
{
    // Remove the __construct method with middleware
    // Instead, use Laravel's route middleware in api.php

    /**
     * Get all tour links
     */
    public function index(Request $request)
    {
        $query = TourLink::with('creator');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'expired') {
                $query->expired();
            } else {
                $query->where('status', $request->status);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tour_name', 'like', "%{$search}%")
                  ->orWhere('link_id', 'like', "%{$search}%")
                  ->orWhere('batch_name', 'like', "%{$search}%");
            });
        }

        $links = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $links
        ]);
    }

    /**
     * Get available batches for dropdown
     */
    public function getBatches()
    {
        $batches = collect();
        
        if (class_exists('App\Models\TourBatch')) {
            $batches = TourBatch::active()->orderBy('year', 'desc')->get();
        }
        
        // Also include unique batch names from existing tours
        $existingBatches = TourLink::distinct('batch_name')->pluck('batch_name');
        
        $allBatches = $batches->pluck('name')->merge($existingBatches)->unique()->sort()->values();

        return response()->json([
            'success' => true,
            'data' => $allBatches
        ]);
    }

    /**
     * Generate a new tour link
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tour_name' => 'required|string|max:255',
            'batch_name' => 'required|string|max:100',
            'expiry_date' => 'required|date|after:today',
            'description' => 'nullable|string',
            'google_drive_folder_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tourLink = TourLink::create([
            'tour_name' => $request->tour_name,
            'batch_name' => $request->batch_name,
            'description' => $request->description,
            'expiry_date' => $request->expiry_date,
            'created_by' => Auth::id(),
            'google_drive_folder_id' => $request->google_drive_folder_id,
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tour link generated successfully',
            'data' => $tourLink->load('creator')
        ], 201);
    }

    /**
     * Get single tour link details
     */
    public function show($id)
    {
        $tourLink = TourLink::with(['creator', 'submissions' => function($q) {
            $q->latest()->take(10);
        }])->findOrFail($id);

        // Get submission statistics
        $stats = [
            'total' => $tourLink->submissions()->count(),
            'pending' => $tourLink->submissions()->where('status', 'pending')->count(),
            'stored' => $tourLink->submissions()->where('status', 'stored')->count(),
            'approved' => $tourLink->submissions()->where('status', 'approved')->count(),
            'rejected' => $tourLink->submissions()->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $tourLink,
            'stats' => $stats
        ]);
    }

    /**
     * Update tour link
     */
    public function update(Request $request, $id)
    {
        $tourLink = TourLink::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tour_name' => 'sometimes|string|max:255',
            'batch_name' => 'sometimes|string|max:100',
            'expiry_date' => 'sometimes|date',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,expired,draft'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tourLink->update($request->only([
            'tour_name', 'batch_name', 'expiry_date', 'description', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Tour link updated successfully',
            'data' => $tourLink
        ]);
    }

    /**
     * Delete tour link
     */
    public function destroy($id)
    {
        $tourLink = TourLink::findOrFail($id);
        
        // Check if there are submissions
        if ($tourLink->submissions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete tour link with existing submissions'
            ], 400);
        }

        $tourLink->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tour link deleted successfully'
        ]);
    }

    /**
     * Get submissions for a specific tour link (for Course Clerk)
     */
    public function getSubmissions($id, Request $request)
    {
        $tourLink = TourLink::findOrFail($id);
        
        $query = $tourLink->submissions()->with('trainee');

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

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * Submit journal (for Trainees)
     */
    public function submitJournal(Request $request, $linkId)
    {
        $tourLink = TourLink::where('link_id', $linkId)
                           ->active()
                           ->firstOrFail();

        // Check if already submitted
        $existingSubmission = TourSubmission::where('tour_link_id', $tourLink->id)
                                            ->where('trainee_id', Auth::id())
                                            ->first();

        if ($existingSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted a journal for this tour'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'journal_content' => 'nullable|string',
            'file_url' => 'nullable|url',
            'google_drive_file_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $submission = TourSubmission::create([
            'tour_link_id' => $tourLink->id,
            'trainee_id' => Auth::id(),
            'trainee_name' => Auth::user()->name,
            'roll_no' => Auth::user()->username,
            'tour_name' => $tourLink->tour_name,
            'journal_content' => $request->journal_content,
            'file_url' => $request->file_url,
            'google_drive_file_id' => $request->google_drive_file_id,
            'status' => 'pending',
            'submitted_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Journal submitted successfully',
            'data' => $submission
        ]);
    }

    /**
 * Get completed tours from tours table
 */
public function getCompletedTours()
{
    try {
        $completedTours = DB::table('tours')
            ->where('end_date', '<', now()->toDateString()) // Use toDateString() for comparison
            ->orderBy('end_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $completedTours,
            'current_date' => now()->toDateString(), // For debugging
            'count' => $completedTours->count()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}