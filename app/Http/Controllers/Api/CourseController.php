<?php
namespace App\Http\Controllers\Api;



use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of courses.
     * GET /api/courses
     */
    public function index(): JsonResponse
    {
        try {
            $courses = Course::orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $courses,
                'message' => 'Courses retrieved successfully'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store a newly created course.
     * POST /api/courses
     */
    public function store(Request $request): JsonResponse
    {
        // Validation rules
        // $validator = Validator::make($request->all(), [
        //     'code' => 'required|string|unique:courses,code|max:50',
        //     'name' => 'required|string|max:255',
        //     'category' => ['required', Rule::in(['Induction', 'In-Service', 'Special'])],
        //     'type' => ['required', Rule::in(['Residential', 'Non-Residential', 'Hybrid'])],
        //     'startDate' => 'required|date|after_or_equal:today',
        //     'endDate' => 'required|date|after:startDate',
        //     'status' => ['required', Rule::in(['Draft', 'Under Review', 'Approved', 'Published', 'Archived'])],
        //     'description' => 'nullable|string',
        //     'capacity' => 'nullable|integer|min:1|max:999',
        //     'notes' => 'nullable|string',
        // ]);
        
        // // Return validation errors if any
        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'errors' => $validator->errors(),
        //         'message' => 'Validation failed'
        //     ], 422);
        // }

        $validator = Validator::make($request->all(), [
        'code' => 'required|string|unique:courses,code|max:50',
        'name' => 'required|string|max:255',
        'category' => ['required', Rule::in(['Induction', 'In-Service', 'Special'])],
        'type' => ['required', Rule::in(['Residential', 'Non-Residential', 'Hybrid'])],
        'startDate' => 'required|date', // Remove the after_or_equal:today rule
        'endDate' => 'required|date|after:startDate',
        'status' => ['required', Rule::in(['Draft', 'Under Review', 'Approved', 'Published', 'Archived'])],
        'description' => 'nullable|string',
        'capacity' => 'nullable|integer|min:1',
        'notes' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
            'message' => 'Validation failed'
        ], 422);
    }
        
        try {
            // Calculate duration days
            $startDate = \Carbon\Carbon::parse($request->startDate);
            $endDate = \Carbon\Carbon::parse($request->endDate);
            $durationDays = $startDate->diffInDays($endDate) + 1;
            
            // Create course
            $course = Course::create([
                'code' => $request->code,
                'name' => $request->name,
                'category' => $request->category,
                'type' => $request->type,
                'start_date' => $request->startDate,
                'end_date' => $request->endDate,
                'duration_days' => $durationDays,
                'status' => $request->status,
                'description' => $request->description,
                'capacity' => $request->capacity,
                'notes' => $request->notes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $course,
                'message' => 'Course created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create course',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified course.
     * GET /api/courses/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $course = Course::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $course,
                'message' => 'Course retrieved successfully'
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve course',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the specified course.
     * PUT /api/courses/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // try {
        //     $course = Course::findOrFail($id);
            
        //     // Validation rules
        //     $validator = Validator::make($request->all(), [
        //         'code' => 'required|string|unique:courses,code,' . $id . '|max:50',
        //         'name' => 'required|string|max:255',
        //         'category' => ['required', Rule::in(['Induction', 'In-Service', 'Special'])],
        //         'type' => ['required', Rule::in(['Residential', 'Non-Residential', 'Hybrid'])],
        //         'startDate' => 'required|date',
        //         'endDate' => 'required|date|after:startDate',
        //         'status' => ['required', Rule::in(['Draft', 'Under Review', 'Approved', 'Published', 'Archived'])],
        //         'description' => 'nullable|string',
        //         'capacity' => 'nullable|integer|min:1',
        //         'notes' => 'nullable|string',
        //     ]);
            
        //     if ($validator->fails()) {
        //         return response()->json([
        //             'success' => false,
        //             'errors' => $validator->errors(),
        //             'message' => 'Validation failed'
        //         ], 422);
        //     }
         try {
        $course = Course::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:courses,code,' . $id . '|max:50',
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(['Induction', 'In-Service', 'Special'])],
            'type' => ['required', Rule::in(['Residential', 'Non-Residential', 'Hybrid'])],
            'startDate' => 'required|date', // Remove the after_or_equal:today rule
            'endDate' => 'required|date|after:startDate',
            'status' => ['required', Rule::in(['Draft', 'Under Review', 'Approved', 'Published', 'Archived'])],
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
            
            // Calculate duration days
            $startDate = \Carbon\Carbon::parse($request->startDate);
            $endDate = \Carbon\Carbon::parse($request->endDate);
            $durationDays = $startDate->diffInDays($endDate) + 1;
            
            // Update course
            $course->update([
                'code' => $request->code,
                'name' => $request->name,
                'category' => $request->category,
                'type' => $request->type,
                'start_date' => $request->startDate,
                'end_date' => $request->endDate,
                'duration_days' => $durationDays,
                'status' => $request->status,
                'description' => $request->description,
                'capacity' => $request->capacity,
                'notes' => $request->notes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $course,
                'message' => 'Course updated successfully'
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update course',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update only the status of a course.
     * PATCH /api/courses/{id}/status
     * 
     * This is the key method for the updateStatus functionality
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            // Find the course or fail with 404
            $course = Course::findOrFail($id);
            
            // Validate the status field only
            $validator = Validator::make($request->all(), [
                'status' => [
                    'required',
                    Rule::in(['Draft', 'Under Review', 'Approved', 'Published', 'Archived'])
                ]
            ]);
            
            // Return validation errors if any
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Invalid status value'
                ], 422);
            }
            
            $oldStatus = $course->status;
            $newStatus = $request->status;
            
            // Additional business logic validation
            // Example: Cannot archive a course that has active enrollments
            if ($newStatus === 'Archived' && $course->hasActiveEnrollments()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot archive course with active enrollments'
                ], 422);
            }
            
            // Example: Cannot publish if dates are invalid
            if ($newStatus === 'Published' && $course->end_date < now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot publish course that has already ended'
                ], 422);
            }
            
            // Update only the status field
            $course->status = $newStatus;
            $course->save();
            
            // You can add event logging here
            // Log::info("Course status changed", [
            //     'course_id' => $course->id,
            //     'old_status' => $oldStatus,
            //     'new_status' => $newStatus,
            //     'user_id' => auth()->id()
            // ]);
            
            return response()->json([
                'success' => true,
                'data' => $course,
                'message' => "Course status updated from {$oldStatus} to {$newStatus} successfully",
                'meta' => [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'updated_at' => $course->updated_at
                ]
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update course status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Bulk update status for multiple courses.
     * PATCH /api/courses/bulk-status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'exists:courses,id',
            'status' => ['required', Rule::in(['Draft', 'Under Review', 'Approved', 'Published', 'Archived'])]
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $updatedCount = Course::whereIn('id', $request->course_ids)
                ->update(['status' => $request->status]);
            
            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} courses updated successfully",
                'data' => [
                    'updated_count' => $updatedCount,
                    'status' => $request->status
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified course.
     * DELETE /api/courses/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $course = Course::findOrFail($id);
            
            // Check if course can be deleted (e.g., no enrollments)
            if ($course->hasEnrollments()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete course with existing enrollments'
                ], 422);
            }
            
            $course->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Course deleted successfully'
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete course',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get courses by status.
     * GET /api/courses/status/{status}
     */
    public function getByStatus(string $status): JsonResponse
    {
        $validStatuses = ['Draft', 'Under Review', 'Approved', 'Published', 'Archived'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status'
            ], 400);
        }
        
        try {
            $courses = Course::where('status', $status)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $courses,
                'message' => "Courses with status {$status} retrieved successfully"
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}