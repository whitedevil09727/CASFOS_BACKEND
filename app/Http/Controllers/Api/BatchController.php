<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Trainee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class BatchController extends Controller
{
    /**
     * Get all batches
     */
    public function index(): JsonResponse
    {
        try {
            $batches = Batch::with('course')->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $batches,
                'message' => 'Batches retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available courses for batch creation (only published courses)
     */
    public function getAvailableCourses(): JsonResponse
    {
        try {
            $courses = Course::where('status', 'Published')
                ->orderBy('start_date', 'desc')
                ->get(['id', 'code', 'name', 'start_date', 'end_date']);
            
            return response()->json([
                'success' => true,
                'data' => $courses,
                'message' => 'Available courses retrieved successfully'
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
     * Get single batch with details
     */
    public function show($id): JsonResponse
    {
        try {
            $batch = Batch::with('course')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $batch,
                'message' => 'Batch retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found'
            ], 404);
        }
    }
    
    /**
     * Create a new batch
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:batches,code|max:50',
            'name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'capacity' => 'required|integer|min:1',
            'status' => ['required', Rule::in(['Draft', 'Active', 'Full', 'Archived'])],
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'lead_instructor' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        try {
            $batch = Batch::create([
                'code' => $request->code,
                'name' => $request->name,
                'course_id' => $request->course_id,
                'capacity' => $request->capacity,
                'status' => $request->status,
                'start_date' => $request->startDate,
                'end_date' => $request->endDate,
                'lead_instructor' => $request->lead_instructor,
                'description' => $request->description,
                'trainee_ids' => [],
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $batch->load('course'),
                'message' => 'Batch created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create batch',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update batch
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $batch = Batch::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|unique:batches,code,' . $id . '|max:50',
                'name' => 'required|string|max:255',
                'course_id' => 'required|exists:courses,id',
                'capacity' => 'required|integer|min:1',
                'status' => ['required', Rule::in(['Draft', 'Active', 'Full', 'Archived'])],
                'startDate' => 'required|date',
                'endDate' => 'required|date|after:startDate',
                'lead_instructor' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            $batch->update([
                'code' => $request->code,
                'name' => $request->name,
                'course_id' => $request->course_id,
                'capacity' => $request->capacity,
                'status' => $request->status,
                'start_date' => $request->startDate,
                'end_date' => $request->endDate,
                'lead_instructor' => $request->lead_instructor,
                'description' => $request->description,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $batch->load('course'),
                'message' => 'Batch updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update batch status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $batch = Batch::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'status' => ['required', Rule::in(['Draft', 'Active', 'Full', 'Archived'])]
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Invalid status value'
                ], 422);
            }
            
            $batch->status = $request->status;
            $batch->save();
            
            return response()->json([
                'success' => true,
                'data' => $batch,
                'message' => 'Batch status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all trainees (for batch assignment)
     */
    public function getTrainees(): JsonResponse
    {
        try {
            $trainees = Trainee::orderBy('name')->get();
            
            return response()->json([
                'success' => true,
                'data' => $trainees,
                'message' => 'Trainees retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get batch with assigned and available trainees
     * This method now ensures trainees are not double-assigned across batches
     */
    public function getBatchWithTrainees($id): JsonResponse
    {
        try {
            $batch = Batch::with('course')->findOrFail($id);
            
            // Get all trainees
            $allTrainees = Trainee::orderBy('name')->get();
            
            // Get IDs of trainees assigned to THIS batch
            $assignedToThisBatch = $batch->trainee_ids ?? [];
            
            // Get IDs of trainees assigned to OTHER batches (excluding this batch)
            $otherBatches = Batch::where('id', '!=', $id)->get();
            $assignedToOtherBatches = [];
            
            foreach ($otherBatches as $otherBatch) {
                $assignedToOtherBatches = array_merge($assignedToOtherBatches, $otherBatch->trainee_ids ?? []);
            }
            $assignedToOtherBatches = array_unique($assignedToOtherBatches);
            
            // Get assigned trainees for THIS batch
            $assignedTrainees = Trainee::whereIn('id', $assignedToThisBatch)->get();
            
            // Get available trainees (not assigned to ANY batch, or assigned to this batch but we're showing them as assigned)
            // Available trainees are those NOT in any batch (including this batch)
            $allAssignedTrainees = array_unique(array_merge($assignedToThisBatch, $assignedToOtherBatches));
            $availableTrainees = Trainee::whereNotIn('id', $allAssignedTrainees)->orderBy('name')->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => [
                        'id' => $batch->id,
                        'code' => $batch->code,
                        'name' => $batch->name,
                        'course' => $batch->course,
                        'capacity' => $batch->capacity,
                        'status' => $batch->status,
                        'trainee_ids' => $assignedToThisBatch,
                        'current_count' => count($assignedToThisBatch),
                        'fill_percentage' => $batch->capacity > 0 ? min(100, (count($assignedToThisBatch) / $batch->capacity) * 100) : 0,
                        'is_full' => count($assignedToThisBatch) >= $batch->capacity,
                        'start_date' => $batch->start_date,
                        'end_date' => $batch->end_date,
                        'lead_instructor' => $batch->lead_instructor,
                        'description' => $batch->description,
                        'created_at' => $batch->created_at,
                        'updated_at' => $batch->updated_at,
                    ],
                    'assigned_trainees' => $assignedTrainees,
                    'available_trainees' => $availableTrainees,
                    'all_trainees' => $allTrainees,
                    'assigned_to_other_batches' => Trainee::whereIn('id', $assignedToOtherBatches)->get()
                ],
                'message' => 'Batch details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batch details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Assign trainees to batch
     * Now checks if trainees are already assigned to any batch
     */
    public function assignTrainees(Request $request, $id): JsonResponse
    {
        try {
            $batch = Batch::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'trainee_ids' => 'required|array',
                'trainee_ids.*' => 'exists:trainees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Check if any of these trainees are already assigned to other batches
            $otherBatches = Batch::where('id', '!=', $id)->get();
            $assignedToOtherBatches = [];
            
            foreach ($otherBatches as $otherBatch) {
                $assignedToOtherBatches = array_merge($assignedToOtherBatches, $otherBatch->trainee_ids ?? []);
            }
            
            $alreadyAssigned = array_intersect($request->trainee_ids, $assignedToOtherBatches);
            
            if (!empty($alreadyAssigned)) {
                $alreadyAssignedNames = Trainee::whereIn('id', $alreadyAssigned)->pluck('name')->implode(', ');
                return response()->json([
                    'success' => false,
                    'message' => "The following trainees are already assigned to other batches: {$alreadyAssignedNames}",
                    'already_assigned' => $alreadyAssigned
                ], 422);
            }
            
            $added = $batch->assignTrainees($request->trainee_ids);
            $warning = null;
            
            if ($batch->current_count > $batch->capacity) {
                $warning = "Batch capacity exceeded by " . ($batch->current_count - $batch->capacity) . " trainees.";
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $batch->load('course'),
                    'added_trainees' => Trainee::whereIn('id', $added)->get(),
                    'current_count' => $batch->current_count,
                    'capacity' => $batch->capacity,
                    'is_full' => $batch->is_full
                ],
                'warning' => $warning,
                'message' => count($added) . ' trainees assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove trainees from batch
     */
    public function removeTrainees(Request $request, $id): JsonResponse
    {
        try {
            $batch = Batch::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'trainee_ids' => 'required|array',
                'trainee_ids.*' => 'exists:trainees,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            $removed = $batch->removeTrainees($request->trainee_ids);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $batch->load('course'),
                    'removed_trainees' => Trainee::whereIn('id', $removed)->get(),
                    'current_count' => $batch->current_count,
                    'capacity' => $batch->capacity
                ],
                'message' => count($removed) . ' trainees removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete batch
     */
    public function destroy($id): JsonResponse
    {
        try {
            $batch = Batch::findOrFail($id);
            $batch->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Batch deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete batch',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}