<?php
// app/Http/Controllers/Api/TraineeTourJournalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourLink;
use App\Models\TraineeTourJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TraineeTourJournalController extends Controller
{
    /**
     * Get all tour journals for the logged-in trainee
     */
    public function index(Request $request)
    {
        try {
            $traineeId = Auth::id();
            
            // Get journals using query builder to avoid model relationship issues
            $journals = DB::table('trainee_tour_journals as j')
                ->leftJoin('tour_links as t', 'j.tour_link_id', '=', 't.id')
                ->where('j.trainee_id', $traineeId)
                ->select(
                    'j.*',
                    't.tour_name',
                    't.location',
                    't.duration',
                    't.oic',
                    't.gl',
                    't.tour_date'
                )
                ->orderBy('j.created_at', 'desc')
                ->paginate(20);

            // Get IDs of tours already submitted
            $submittedTourIds = DB::table('trainee_tour_journals')
                ->where('trainee_id', $traineeId)
                ->pluck('tour_link_id')
                ->toArray();
            
            // Get available tours (not submitted yet)
            $availableTours = DB::table('tour_links')
                ->where('status', 'active')
                ->where('expiry_date', '>=', now())
                ->whereNotIn('id', $submittedTourIds)
                ->get();

            // Statistics using query builder
            $stats = [
                'total' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->count(),
                'pending' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'pending')->count(),
                'uploaded' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'uploaded')->count(),
                'under_review' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'under_review')->count(),
                'approved' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'approved')->count(),
                'rejected' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'rejected')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $journals,
                'available_tours' => $availableTours,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch journals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available tour links for journal submission
     */
    public function getAvailableTours()
    {
        try {
            $traineeId = Auth::id();
            
            // Get IDs of tours already submitted
            $submittedTourIds = DB::table('trainee_tour_journals')
                ->where('trainee_id', $traineeId)
                ->pluck('tour_link_id')
                ->toArray();
            
            $availableTours = DB::table('tour_links')
                ->where('status', 'active')
                ->where('expiry_date', '>=', now())
                ->whereNotIn('id', $submittedTourIds)
                ->orderBy('expiry_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $availableTours
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available tours: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single journal details
     */
    public function show($id)
    {
        try {
            $traineeId = Auth::id();
            
            $journal = DB::table('trainee_tour_journals as j')
                ->leftJoin('tour_links as t', 'j.tour_link_id', '=', 't.id')
                ->leftJoin('users as u', 'j.reviewed_by', '=', 'u.id')
                ->where('j.id', $id)
                ->where('j.trainee_id', $traineeId)
                ->select(
                    'j.*',
                    't.tour_name',
                    't.location',
                    't.duration',
                    't.oic',
                    't.gl',
                    't.tour_date',
                    'u.name as reviewer_name'
                )
                ->first();

            if (!$journal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Journal not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $journal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch journal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a new tour journal
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tour_link_id' => 'required|exists:tour_links,id',
                'title' => 'required|string|max:255',
                'content' => 'nullable|string',
                'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:25600'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if tour link exists and is active using DB
            $tourLink = DB::table('tour_links')
                ->where('id', $request->tour_link_id)
                ->where('status', 'active')
                ->where('expiry_date', '>=', now())
                ->first();

            if (!$tourLink) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tour link is not available or has expired'
                ], 400);
            }

            // Check if already submitted
            $existingJournal = DB::table('trainee_tour_journals')
                ->where('tour_link_id', $request->tour_link_id)
                ->where('trainee_id', Auth::id())
                ->exists();

            if ($existingJournal) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted a journal for this tour'
                ], 400);
            }

            // Handle file upload
            $fileUrl = null;
            $fileName = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . Str::slug($tourLink->tour_name) . '.' . $file->getClientOriginalExtension();
                
                // Store file
                $path = $file->storeAs('tour-journals/' . Auth::id(), $fileName, 'public');
                $fileUrl = asset('storage/' . $path);
            }

            // Create journal entry using DB
            $journalId = DB::table('trainee_tour_journals')->insertGetId([
                'tour_link_id' => $request->tour_link_id,
                'trainee_id' => Auth::id(),
                'title' => $request->title,
                'content' => $request->content,
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'status' => $request->hasFile('file') ? 'uploaded' : 'pending',
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $journal = DB::table('trainee_tour_journals')->where('id', $journalId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Journal submitted successfully',
                'data' => $journal
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit journal: ' . $e->getMessage()
            ], 500);
        }
    }

/**
 * Update an existing journal
 * PUT/POST /api/trainee/tour-journals/{id}
 */
public function update(Request $request, $id): JsonResponse
{
    try {
        $traineeId = Auth::id();
        
        // Find the journal
        $journal = DB::table('trainee_tour_journals')
            ->where('id', $id)
            ->where('trainee_id', $traineeId)
            ->first();

        if (!$journal) {
            return response()->json([
                'success' => false,
                'message' => 'Journal not found'
            ], 404);
        }

        // Allow updates for pending, rejected, and uploaded statuses
        // Block only approved and under_review
        if (in_array($journal->status, ['approved', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Journal cannot be updated. Current status: ' . $journal->status . '. Only pending, rejected, or uploaded journals can be updated.'
            ], 400);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:25600'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = [
            'updated_at' => now()
        ];

        // Update title
        if ($request->has('title')) {
            $updateData['title'] = $request->title;
        }

        // Update content
        if ($request->has('content')) {
            $updateData['content'] = $request->content;
        }

        // Handle file deletion
        if ($request->input('delete_file') === 'true' && $journal->file_url) {
            // Delete old file from storage
            $oldPath = str_replace('/storage/', '', $journal->file_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $updateData['file_url'] = null;
            $updateData['file_name'] = null;
            $updateData['file_size'] = null;
            $updateData['file_type'] = null;
            $updateData['status'] = 'pending';
        }

        // Handle new file upload
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($journal->file_url && $request->input('delete_file') !== 'true') {
                $oldPath = str_replace('/storage/', '', $journal->file_url);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . Str::slug($journal->title ?? 'journal') . '.' . $file->getClientOriginalExtension();
            $fileSize = $file->getSize();
            $fileType = $file->getMimeType();
            $path = $file->storeAs('tour-journals/' . $traineeId, $fileName, 'public');
            $fileUrl = asset('storage/' . $path);
            
            $updateData['file_url'] = $fileUrl;
            $updateData['file_name'] = $fileName;
            $updateData['file_size'] = $fileSize;
            $updateData['file_type'] = $fileType;
            $updateData['status'] = 'uploaded';
            $updateData['submitted_at'] = now();
        }

        // If no file changes but content/title updated, keep existing status
        if (!isset($updateData['status']) && !$request->input('delete_file') && !$request->hasFile('file')) {
            $updateData['status'] = $journal->status;
        }

        // Perform update
        DB::table('trainee_tour_journals')
            ->where('id', $id)
            ->update($updateData);

        // Get updated journal
        $updatedJournal = DB::table('trainee_tour_journals')
            ->where('id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Journal updated successfully',
            'data' => $updatedJournal
        ]);

    } catch (\Exception $e) {
        \Log::error('Update journal error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update journal: ' . $e->getMessage()
        ], 500);
    }
}

    /**
 * Delete a journal
 * DELETE /api/trainee/tour-journals/{id}
 */
public function destroy($id): JsonResponse
{
    try {
        \Log::info('Delete method called for ID: ' . $id);
        
        $traineeId = Auth::id();
        
        $journal = DB::table('trainee_tour_journals')
            ->where('id', $id)
            ->where('trainee_id', $traineeId)
            ->first();

        if (!$journal) {
            return response()->json([
                'success' => false,
                'message' => 'Journal not found'
            ], 404);
        }

        // Delete file if exists
        if ($journal->file_url) {
            $path = str_replace('/storage/', '', $journal->file_url);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        DB::table('trainee_tour_journals')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Journal deleted successfully'
        ]);

    } catch (\Exception $e) {
        \Log::error('Delete journal error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete journal: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Get journal statistics
     */
    public function statistics()
    {
        try {
            $traineeId = Auth::id();
            
            $submittedTourIds = DB::table('trainee_tour_journals')
                ->where('trainee_id', $traineeId)
                ->pluck('tour_link_id')
                ->toArray();
            
            $availableToursCount = DB::table('tour_links')
                ->where('status', 'active')
                ->where('expiry_date', '>=', now())
                ->whereNotIn('id', $submittedTourIds)
                ->count();
            
            $stats = [
                'total' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->count(),
                'pending' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'pending')->count(),
                'uploaded' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'uploaded')->count(),
                'under_review' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'under_review')->count(),
                'approved' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'approved')->count(),
                'rejected' => DB::table('trainee_tour_journals')->where('trainee_id', $traineeId)->where('status', 'rejected')->count(),
                'available_tours' => $availableToursCount
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    
}