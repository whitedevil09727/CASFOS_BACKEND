<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimetableSession;
use App\Models\Course;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TimetableController extends Controller
{
    // Days and hours constants
    private const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    private const HOURS = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17];
    
    /**
     * Get all timetable sessions
     */
    public function index(): JsonResponse
    {
        try {
            $sessions = TimetableSession::with(['course', 'batch'])
                ->orderBy('day')
                ->orderBy('start_hour')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $sessions,
                'message' => 'Timetable retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve timetable',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get sessions grouped by day for grid display
     */
    public function getGrid(): JsonResponse
    {
        try {
            $sessions = TimetableSession::with(['course', 'batch'])
                ->orderBy('day')
                ->orderBy('start_hour')
                ->get();
            
            $grid = [];
            foreach (self::DAYS as $day) {
                $grid[$day] = $sessions->filter(fn($s) => $s->day === $day)->values();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sessions' => $sessions,
                    'grid' => $grid,
                    'days' => self::DAYS,
                    'hours' => self::HOURS,
                ],
                'message' => 'Timetable grid retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve timetable grid',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a single session
     */
    public function show($id): JsonResponse
    {
        try {
            $session = TimetableSession::with(['course', 'batch'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $session,
                'message' => 'Session retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found'
            ], 404);
        }
    }
    
    /**
     * Create a new timetable session
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'day' => ['required', Rule::in(self::DAYS)],
            'startHour' => ['required', 'integer', Rule::in(self::HOURS)],
            'duration' => ['required', 'integer', 'min:1', 'max:4'],
            'subject' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
            'topic' => 'nullable|string|max:500',
            'room' => 'nullable|string|max:100',
            'course_id' => 'nullable|exists:courses,id',
            'batch_id' => 'nullable|exists:batches,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
        
        try {
            // Check for conflicts
            $conflict = TimetableSession::where('day', $request->day)
                ->where(function($query) use ($request) {
                    $start = $request->startHour;
                    $end = $request->startHour + $request->duration;
                    $query->where(function($q) use ($start, $end) {
                        $q->where('start_hour', '<', $end)
                          ->whereRaw('start_hour + duration > ?', [$start]);
                    });
                })->first();
            
            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time slot conflict with existing session',
                    'conflict' => $conflict
                ], 409);
            }
            
            $session = TimetableSession::create([
                'day' => $request->day,
                'start_hour' => $request->startHour,
                'duration' => $request->duration,
                'subject' => $request->subject,
                'faculty' => $request->faculty,
                'topic' => $request->topic,
                'room' => $request->room,
                'course_id' => $request->course_id,
                'batch_id' => $request->batch_id,
                'is_substituted' => false,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $session->load(['course', 'batch']),
                'message' => 'Session created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a timetable session
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $session = TimetableSession::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'day' => ['required', Rule::in(self::DAYS)],
                'startHour' => ['required', 'integer', Rule::in(self::HOURS)],
                'duration' => ['required', 'integer', 'min:1', 'max:4'],
                'subject' => 'required|string|max:255',
                'faculty' => 'required|string|max:255',
                'topic' => 'nullable|string|max:500',
                'room' => 'nullable|string|max:100',
                'course_id' => 'nullable|exists:courses,id',
                'batch_id' => 'nullable|exists:batches,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Check for conflicts excluding current session
            $conflict = TimetableSession::where('id', '!=', $id)
                ->where('day', $request->day)
                ->where(function($query) use ($request) {
                    $start = $request->startHour;
                    $end = $request->startHour + $request->duration;
                    $query->where(function($q) use ($start, $end) {
                        $q->where('start_hour', '<', $end)
                          ->whereRaw('start_hour + duration > ?', [$start]);
                    });
                })->first();
            
            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time slot conflict with existing session',
                    'conflict' => $conflict
                ], 409);
            }
            
            $session->update([
                'day' => $request->day,
                'start_hour' => $request->startHour,
                'duration' => $request->duration,
                'subject' => $request->subject,
                'faculty' => $request->faculty,
                'topic' => $request->topic,
                'room' => $request->room,
                'course_id' => $request->course_id,
                'batch_id' => $request->batch_id,
            ]);
            
            // If faculty changed and it was a substitution, reset substitution flag
            if ($session->is_substituted && $session->original_faculty !== $request->faculty) {
                $session->is_substituted = false;
                $session->original_faculty = null;
                $session->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => $session->load(['course', 'batch']),
                'message' => 'Session updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Substitute faculty for a session
     */
    public function substituteFaculty(Request $request, $id): JsonResponse
    {
        try {
            $session = TimetableSession::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'faculty' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            $session->substituteFaculty($request->faculty);
            
            return response()->json([
                'success' => true,
                'data' => $session->load(['course', 'batch']),
                'message' => 'Faculty substituted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to substitute faculty',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Revert faculty substitution
     */
    public function revertSubstitution($id): JsonResponse
    {
        try {
            $session = TimetableSession::findOrFail($id);
            
            if (!$session->is_substituted) {
                return response()->json([
                    'success' => false,
                    'message' => 'This session does not have a substitution'
                ], 400);
            }
            
            $session->revertSubstitution();
            
            return response()->json([
                'success' => true,
                'data' => $session->load(['course', 'batch']),
                'message' => 'Substitution reverted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revert substitution',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get sessions by day
     */
    public function getByDay($day): JsonResponse
    {
        if (!in_array($day, self::DAYS)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid day'
            ], 400);
        }
        
        try {
            $sessions = TimetableSession::where('day', $day)
                ->orderBy('start_hour')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $sessions,
                'message' => "Sessions for {$day} retrieved successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get sessions by faculty
     */
    public function getByFaculty($faculty): JsonResponse
    {
        try {
            $sessions = TimetableSession::where('faculty', 'like', "%{$faculty}%")
                ->orderBy('day')
                ->orderBy('start_hour')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $sessions,
                'message' => 'Sessions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a session
     */
    public function destroy($id): JsonResponse
    {
        try {
            $session = TimetableSession::findOrFail($id);
            $session->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available faculty list
     */
    public function getFacultyList(): JsonResponse
    {
        try {
            // Get unique faculty from timetable sessions
            $faculty = TimetableSession::distinct()->pluck('faculty');
            
            // You can also add faculty from users table if needed
            $usersFaculty = \App\Models\User::where('role', 'faculty')
                ->pluck('name');
            
            $allFaculty = $faculty->merge($usersFaculty)->unique()->sort()->values();
            
            return response()->json([
                'success' => true,
                'data' => $allFaculty,
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
     * Get subject list
     */
    public function getSubjectList(): JsonResponse
    {
        try {
            // Get unique subjects from timetable sessions
            $subjects = TimetableSession::distinct()->pluck('subject');
            
            // Add default subjects if needed
            $defaultSubjects = [
                'Forest Ecology', 'Wildlife Management', 'GIS & Remote Sensing',
                'Forest Laws & Policy', 'Silviculture', 'Biodiversity Conservation',
                'Environmental Impact Assessment', 'Forest Working Plan', 'Agroforestry',
                'Carbon Sequestration', 'Physical Training', 'Field Exercises',
            ];
            
            $allSubjects = $subjects->merge($defaultSubjects)->unique()->sort()->values();
            
            return response()->json([
                'success' => true,
                'data' => $allSubjects,
                'message' => 'Subject list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subject list',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}