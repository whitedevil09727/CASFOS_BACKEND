<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\Batch;
use App\Models\User;
use App\Models\Trainee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; 
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TourController extends Controller
{
    /**
     * Get all tours with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Tour::with(['batch', 'oic', 'groupLeader']);
            
            // Filter by status
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'upcoming':
                        $query->upcoming();
                        break;
                    case 'in-progress':
                        $query->inProgress();
                        break;
                    case 'completed':
                        $query->completed();
                        break;
                }
            }
            
            // Filter by batch
            if ($request->has('batch_id')) {
                $query->where('batch_id', $request->batch_id);
            }
            
            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%")
                      ->orWhereHas('batch', function($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%")
                             ->orWhere('code', 'like', "%{$search}%");
                      });
                });
            }
            
            $tours = $query->orderBy('start_date', 'desc')->get();
            
            // Transform data to match frontend format
            $tours->transform(function ($tour) {
                return [
                    'id' => $tour->id,
                    'code' => $tour->code,
                    'name' => $tour->name,
                    'batchId' => $tour->batch_id,
                    'batch' => $tour->batch ? [
                        'id' => $tour->batch->id,
                        'name' => $tour->batch->name,
                        'code' => $tour->batch->code,
                    ] : null,
                    'startDate' => $tour->start_date instanceof Carbon ? $tour->start_date->format('Y-m-d') : $tour->start_date,
                    'endDate' => $tour->end_date instanceof Carbon ? $tour->end_date->format('Y-m-d') : $tour->end_date,
                    'location' => $tour->location,
                    'journalDueDate' => $tour->journal_due_date instanceof Carbon ? $tour->journal_due_date->format('Y-m-d') : $tour->journal_due_date,
                    'oicId' => $tour->oic_id,
                    'oic' => $tour->oic ? [
                        'id' => $tour->oic->id,
                        'name' => $tour->oic->name,
                        'email' => $tour->oic->email,
                    ] : null,
                    'glId' => $tour->gl_id,
                    'groupLeader' => $tour->groupLeader ? [
                        'id' => $tour->groupLeader->id,
                        'name' => $tour->groupLeader->name,
                        'roll_number' => $tour->groupLeader->roll_number,
                    ] : null,
                    'facultyIds' => $tour->faculty_ids ?? [],
                    'description' => $tour->description,
                    'status' => $tour->status,
                    'durationDays' => $tour->duration_days,
                    'created_at' => $tour->created_at,
                    'updated_at' => $tour->updated_at,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $tours,
                'message' => 'Tours retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tours',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get tour statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $upcoming = Tour::upcoming()->count();
            $inProgress = Tour::inProgress()->count();
            $completed = Tour::completed()->count();
            $total = Tour::count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'upcoming' => $upcoming,
                    'inProgress' => $inProgress,
                    'completed' => $completed,
                    'total' => $total,
                ],
                'message' => 'Tour statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single tour
     */
    public function show($id): JsonResponse
    {
        try {
            $tour = Tour::with(['batch', 'oic', 'groupLeader', 'itineraries'])
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tour->id,
                    'code' => $tour->code,
                    'name' => $tour->name,
                    'batchId' => $tour->batch_id,
                    'batch' => $tour->batch,
                    'startDate' => $tour->start_date instanceof Carbon ? $tour->start_date->format('Y-m-d') : $tour->start_date,
                    'endDate' => $tour->end_date instanceof Carbon ? $tour->end_date->format('Y-m-d') : $tour->end_date,
                    'location' => $tour->location,
                    'journalDueDate' => $tour->journal_due_date instanceof Carbon ? $tour->journal_due_date->format('Y-m-d') : $tour->journal_due_date,
                    'oicId' => $tour->oic_id,
                    'oic' => $tour->oic,
                    'glId' => $tour->gl_id,
                    'groupLeader' => $tour->groupLeader,
                    'facultyIds' => $tour->faculty_ids ?? [],
                    'description' => $tour->description,
                    'itineraries' => $tour->itineraries,
                    'status' => $tour->status,
                    'durationDays' => $tour->duration_days,
                ],
                'message' => 'Tour retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tour not found'
            ], 404);
        }
    }
    
    // /**
    //  * Create a new tour
    //  */
    // public function store(Request $request): JsonResponse
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255',
    //         'batchId' => 'required|exists:batches,id',
    //         'startDate' => 'required|date',
    //         'endDate' => 'required|date|after:startDate',
    //         'location' => 'required|string|max:255',
    //         'journalDueDate' => 'required|date|after:endDate',
    //         'oicId' => 'nullable|exists:users,id',
    //         'glId' => 'nullable|exists:trainees,id',
    //         'facultyIds' => 'nullable|array',
    //         'facultyIds.*' => 'exists:users,id',
    //         'description' => 'nullable|string',
    //     ]);
        
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $validator->errors(),
    //             'message' => 'Validation failed'
    //         ], 422);
    //     }
        
    //     try {
    //         $tour = Tour::create([
    //             'name' => $request->name,
    //             'batch_id' => $request->batchId,
    //             'start_date' => $request->startDate,
    //             'end_date' => $request->endDate,
    //             'location' => $request->location,
    //             'journal_due_date' => $request->journalDueDate,
    //             'oic_id' => $request->oicId,
    //             'gl_id' => $request->glId,
    //             'faculty_ids' => $request->facultyIds ?? [],
    //             'description' => $request->description,
    //         ]);
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => $tour->load(['batch', 'oic', 'groupLeader']),
    //             'message' => 'Tour created successfully'
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create tour',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
    /**
 * Create a new tour
 */
public function store(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'batchId' => 'required|exists:batches,id',
        'startDate' => 'required|date',
        'endDate' => 'required|date|after:startDate',
        'location' => 'required|string|max:255',
        'journalDueDate' => 'required|date|after:endDate',
        'oicId' => 'nullable|exists:users,id',
        'glId' => 'nullable|exists:trainees,id',
        'facultyIds' => 'nullable|array',
        'facultyIds.*' => 'exists:users,id',
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
        // Generate unique code before creating
        $code = $this->generateUniqueTourCode($request->batchId);
        
        $tour = Tour::create([
            'name' => $request->name,
            'batch_id' => $request->batchId,
            'start_date' => $request->startDate,
            'end_date' => $request->endDate,
            'location' => $request->location,
            'journal_due_date' => $request->journalDueDate,
            'oic_id' => $request->oicId,
            'gl_id' => $request->glId,
            'faculty_ids' => $request->facultyIds ?? [],
            'description' => $request->description,
            'code' => $code,
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $tour->load(['batch', 'oic', 'groupLeader']),
            'message' => 'Tour created successfully'
        ], 201);
    } catch (\Illuminate\Database\QueryException $e) {
        // Handle duplicate key error
        if ($e->errorInfo[1] == 23505) { // PostgreSQL duplicate key error code
            return response()->json([
                'success' => false,
                'message' => 'A tour with this code already exists. Please try again.',
                'error' => 'Duplicate tour code'
            ], 409);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to create tour',
            'error' => $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create tour',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Generate a unique tour code
 */
private function generateUniqueTourCode($batchId): string
{
    $batch = Batch::find($batchId);
    $batchPrefix = $batch ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $batch->name), 0, 3)) : 'TOUR';
    
    $maxAttempts = 10;
    for ($i = 1; $i <= $maxAttempts; $i++) {
        $existingCount = Tour::where('batch_id', $batchId)->count();
        $nextNumber = $existingCount + $i;
        $code = $batchPrefix . "-TOUR-" . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
        
        if (!Tour::where('code', $code)->exists()) {
            return $code;
        }
    }
    
    // Fallback with timestamp
    return $batchPrefix . "-TOUR-" . time();
}
    /**
     * Update a tour
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $tour = Tour::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'batchId' => 'required|exists:batches,id',
                'startDate' => 'required|date',
                'endDate' => 'required|date|after:startDate',
                'location' => 'required|string|max:255',
                'journalDueDate' => 'required|date|after:endDate',
                'oicId' => 'nullable|exists:users,id',
                'glId' => 'nullable|exists:trainees,id',
                'facultyIds' => 'nullable|array',
                'facultyIds.*' => 'exists:users,id',
                'description' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            $tour->update([
                'name' => $request->name,
                'batch_id' => $request->batchId,
                'start_date' => $request->startDate,
                'end_date' => $request->endDate,
                'location' => $request->location,
                'journal_due_date' => $request->journalDueDate,
                'oic_id' => $request->oicId,
                'gl_id' => $request->glId,
                'faculty_ids' => $request->facultyIds ?? [],
                'description' => $request->description,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $tour->load(['batch', 'oic', 'groupLeader']),
                'message' => 'Tour updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tour',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a tour
     */
    public function destroy($id): JsonResponse
    {
        try {
            $tour = Tour::findOrFail($id);
            $tour->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Tour deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tour',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available faculty for dropdown
     */
    public function getFacultyList(): JsonResponse
    {
        try {
            $faculty = User::where('role', 'faculty')
                ->orWhere('role', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            
            return response()->json([
                'success' => true,
                'data' => $faculty,
                'message' => 'Faculty list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve faculty list',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available batches for dropdown
     */
    public function getBatchesList(): JsonResponse
    {
        try {
            $batches = Batch::where('status', '!=', 'Archived')
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
            
            return response()->json([
                'success' => true,
                'data' => $batches,
                'message' => 'Batches list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batches list',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get trainees for a specific batch (for Group Leader selection)
     */
    public function getBatchTrainees($batchId): JsonResponse
    {
        try {
            $batch = Batch::findOrFail($batchId);
            $trainees = Trainee::whereIn('id', $batch->trainee_ids ?? [])
                ->orderBy('name')
                ->get(['id', 'name', 'roll_number']);
            
            return response()->json([
                'success' => true,
                'data' => $trainees,
                'message' => 'Batch trainees retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve batch trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }


       /**
     * Get completed tours
     */
public function getCompletedTours()
{
    try {
        // Check if batches table exists and has data
        $batchesExist = DB::table('information_schema.tables')
            ->where('table_name', 'batches')
            ->exists();
        
        if ($batchesExist) {
            // Check if year column exists
            $yearColumnExists = DB::table('information_schema.columns')
                ->where('table_name', 'batches')
                ->where('column_name', 'year')
                ->exists();
            
            if ($yearColumnExists) {
                // Get tours with batch information including year
                $completedTours = DB::table('tours')
                    ->leftJoin('batches', 'tours.batch_id', '=', 'batches.id')
                    ->where('tours.end_date', '<', now()->toDateString())
                    ->select(
                        'tours.*',
                        'batches.name as batch_name',
                        'batches.code as batch_code',
                        'batches.year as batch_year'
                    )
                    ->orderBy('tours.end_date', 'desc')
                    ->get();
            } else {
                // Get tours without year column
                $completedTours = DB::table('tours')
                    ->leftJoin('batches', 'tours.batch_id', '=', 'batches.id')
                    ->where('tours.end_date', '<', now()->toDateString())
                    ->select(
                        'tours.*',
                        'batches.name as batch_name',
                        'batches.code as batch_code'
                    )
                    ->orderBy('tours.end_date', 'desc')
                    ->get();
            }
        } else {
            // Fallback: just get tours without batch join
            $completedTours = DB::table('tours')
                ->where('end_date', '<', now()->toDateString())
                ->select('*', DB::raw("CONCAT('Batch ', batch_id) as batch_name"))
                ->orderBy('end_date', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $completedTours,
            'count' => $completedTours->count()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch completed tours: ' . $e->getMessage()
        ], 500);
    }
}
}