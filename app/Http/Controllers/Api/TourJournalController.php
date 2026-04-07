<?php
// app/Http/Controllers/Api/TourJournalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Tour;
use App\Models\TourJournal;
use App\Models\TourEnrollment;
use App\Models\Batch;
use App\Models\User;
use Carbon\Carbon;
// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
// use Carbon\Carbon;

class TourJournalController extends Controller
{
    /**
     * Get all batches with tour information
     * GET /api/tour-journals/batches
     */
    public function getBatches(): JsonResponse
    {
        try {
            $batches = Batch::with(['tours' => function($query) {
                $query->orderBy('start_date', 'desc');
            }])->get();
            
            // If no batches exist, return empty array
            if ($batches->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $result = $batches->map(function($batch) {
                return [
                    'id' => (string)$batch->id,
                    'name' => $batch->name,
                    'course' => $batch->course_name ?? $batch->name,
                    'tours' => $batch->tours->map(function($tour) {
                        return [
                            'id' => (string)$tour->id,
                            'name' => $tour->name,
                            'deadline' => $tour->journal_due_date ? Carbon::parse($tour->journal_due_date)->format('Y-m-d') : null,
                            'start_date' => $tour->start_date ? Carbon::parse($tour->start_date)->format('Y-m-d') : null,
                            'end_date' => $tour->end_date ? Carbon::parse($tour->end_date)->format('Y-m-d') : null,
                            'status' => $tour->status,
                            'submitted_count' => $tour->submitted_count ?? 0,
                            'pending_count' => $tour->pending_count ?? 0,
                            'completion_rate' => $tour->completion_rate ?? 0
                        ];
                    })
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getBatches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch batches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get tours for a specific batch
     * GET /api/tour-journals/batches/{batchId}/tours
     */
    public function getBatchTours($batchId): JsonResponse
    {
        try {
            $tours = Tour::where('batch_id', $batchId)
                ->orderBy('start_date', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $tours->map(function($tour) {
                    return [
                        'id' => (string)$tour->id,
                        'name' => $tour->name,
                        'deadline' => $tour->journal_due_date ? Carbon::parse($tour->journal_due_date)->format('Y-m-d') : null,
                        'start_date' => $tour->start_date ? Carbon::parse($tour->start_date)->format('Y-m-d') : null,
                        'end_date' => $tour->end_date ? Carbon::parse($tour->end_date)->format('Y-m-d') : null,
                        'status' => $tour->status,
                        'submitted_count' => $tour->submitted_count ?? 0,
                        'pending_count' => $tour->pending_count ?? 0
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tours',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // /**
    //  * Get tour journals for monitoring
    //  * GET /api/tour-journals/monitoring
    //  */
    // public function getMonitoring(Request $request): JsonResponse
    // {
    //     try {
    //         $batchId = $request->batch_id;
    //         $tourId = $request->tour_id;
            
    //         $query = TourJournal::with(['trainee', 'tour']);
            
    //         if ($batchId) {
    //             $query->whereHas('tour', function($q) use ($batchId) {
    //                 $q->where('batch_id', $batchId);
    //             });
    //         }
            
    //         if ($tourId) {
    //             $query->where('tour_id', $tourId);
    //         }
            
    //         $journals = $query->orderBy('created_at', 'desc')->get();
            
    //         $result = $journals->map(function($journal) {
    //             return [
    //                 'id' => (string)$journal->id,
    //                 'trainee_id' => (string)$journal->trainee_id,
    //                 'trainee_name' => $journal->trainee->name,
    //                 'roll_no' => $journal->trainee->roll_no ?? 'N/A',
    //                 'tour_id' => (string)$journal->tour_id,
    //                 'tour_name' => $journal->tour->name,
    //                 'journal_link' => $journal->journal_link,
    //                 'status' => $journal->status,
    //                 'submitted_at' => $journal->submitted_at ? $journal->submitted_at->format('Y-m-d') : null,
    //                 'remarks' => $journal->remarks,
    //                 'created_at' => $journal->created_at
    //             ];
    //         });
            
    //         // Get summary statistics
    //         $summary = [
    //             'total' => $journals->count(),
    //             'submitted' => $journals->where('status', 'submitted')->count(),
    //             'pending' => $journals->where('status', 'pending')->count(),
    //             'approved' => $journals->where('status', 'approved')->count(),
    //             'rejected' => $journals->where('status', 'rejected')->count()
    //         ];
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => $result,
    //             'summary' => $summary
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to fetch monitoring data',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
 * Get tour journals for monitoring
 * GET /api/tour-journals/monitoring
 */
public function getMonitoring(Request $request): JsonResponse
{
    try {
        $batchId = $request->batch_id;
        $tourId = $request->tour_id;
        
        $query = DB::table('trainee_tour_journals as j')
            ->leftJoin('users as u', 'j.trainee_id', '=', 'u.id')
            ->leftJoin('tour_links as t', 'j.tour_link_id', '=', 't.id')
            ->select(
                'j.id',
                'j.trainee_id',
                'u.name as trainee_name',
                'u.username as roll_no',
                'j.tour_link_id as tour_id',
                't.tour_name as tour_name',
                'j.file_url as journal_link',
                'j.status',
                'j.submitted_at',
                'j.admin_remarks as remarks',
                'j.content',
                'j.title',
                'j.created_at'
            );
        
        // If filtering by batch, we need to join through tours table
        if ($batchId) {
            $query->leftJoin('tours as tour', 't.id', '=', 'tour.id')
                  ->where('tour.batch_id', $batchId);
        }
        
        // Filter by specific tour
        if ($tourId) {
            $query->where('j.tour_link_id', $tourId);
        }
        
        $journals = $query->orderBy('j.created_at', 'desc')->get();
        
        $result = $journals->map(function($journal) {
            return [
                'id' => (string)$journal->id,
                'trainee_id' => (string)$journal->trainee_id,
                'trainee_name' => $journal->trainee_name,
                'roll_no' => $journal->roll_no ?? 'N/A',
                'tour_id' => (string)$journal->tour_id,
                'tour_name' => $journal->tour_name,
                'title' => $journal->title,
                'content' => $journal->content,
                'journal_link' => $journal->journal_link,
                'status' => $journal->status,
                'submitted_at' => $journal->submitted_at ? Carbon::parse($journal->submitted_at)->format('Y-m-d H:i:s') : null,
                'remarks' => $journal->remarks,
                'created_at' => $journal->created_at
            ];
        });
        
        $summary = [
            'total' => $journals->count(),
            'submitted' => $journals->where('status', 'uploaded')->count() + $journals->where('status', 'under_review')->count(),
            'pending' => $journals->where('status', 'pending')->count(),
            'approved' => $journals->where('status', 'approved')->count(),
            'rejected' => $journals->where('status', 'rejected')->count()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $result,
            'summary' => $summary
        ]);
    } catch (\Exception $e) {
        \Log::error('Error in getMonitoring: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch monitoring data: ' . $e->getMessage()
        ], 500);
    }
}
    
    /**
     * Get detailed view for a specific tour
     * GET /api/tour-journals/tours/{tourId}/details
     */
    public function getTourDetails($tourId): JsonResponse
    {
        try {
            $tour = Tour::with(['batch', 'journals.trainee'])->findOrFail($tourId);
            
            // Get all trainees enrolled in this tour
            $enrollments = TourEnrollment::where('tour_id', $tourId)
                ->with('trainee')
                ->get();
            
            $journals = [];
            foreach ($enrollments as $enrollment) {
                $journal = $tour->journals->where('trainee_id', $enrollment->trainee_id)->first();
                $journals[] = [
                    'id' => (string)$enrollment->trainee->id,
                    'name' => $enrollment->trainee->name,
                    'roll_no' => $enrollment->trainee->roll_no ?? 'N/A',
                    'status' => $journal ? $journal->status : 'pending',
                    'submitted_at' => $journal && $journal->submitted_at ? $journal->submitted_at->format('Y-m-d') : null,
                    'journal_link' => $journal ? $journal->journal_link : null,
                    'remarks' => $journal ? $journal->remarks : null,
                    'journal_id' => $journal ? (string)$journal->id : null
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tour' => [
                        'id' => (string)$tour->id,
                        'name' => $tour->name,
                        'description' => $tour->description,
                        'batch_name' => $tour->batch->name,
                        'start_date' => $tour->start_date ? Carbon::parse($tour->start_date)->format('Y-m-d') : null,
                        'end_date' => $tour->end_date ? Carbon::parse($tour->end_date)->format('Y-m-d') : null,
                        'deadline' => $tour->journal_due_date ? Carbon::parse($tour->journal_due_date)->format('Y-m-d') : null,
                        'status' => $tour->status,
                        'code' => $tour->code,
                        'location' => $tour->location
                    ],
                    'journals' => $journals,
                    'summary' => [
                        'total' => count($journals),
                        'submitted' => collect($journals)->where('status', 'submitted')->count(),
                        'pending' => collect($journals)->where('status', 'pending')->count(),
                        'completion_rate' => count($journals) > 0 
                            ? round((collect($journals)->where('status', 'submitted')->count() / count($journals)) * 100, 1) 
                            : 0
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tour details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Extend journal deadline for a tour
     * POST /api/tour-journals/tours/{tourId}/extend-deadline
     */
    public function extendDeadline(Request $request, $tourId): JsonResponse
    {
        $request->validate([
            'new_deadline' => 'required|date|after:today'
        ]);
        
        try {
            $tour = Tour::findOrFail($tourId);
            $tour->journal_due_date = Carbon::parse($request->new_deadline);
            $tour->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Deadline extended successfully',
                'data' => [
                    'tour_id' => (string)$tour->id,
                    'new_deadline' => $tour->journal_due_date->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend deadline',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Approve a journal submission
     * POST /api/tour-journals/journals/{journalId}/approve
     */
    public function approveJournal($journalId): JsonResponse
    {
        try {
            $journal = TourJournal::findOrFail($journalId);
            $journal->status = 'approved';
            $journal->approved_at = now();
            $journal->approved_by = auth()->id();
            $journal->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Journal approved successfully',
                'data' => $journal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve journal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject a journal submission
     * POST /api/tour-journals/journals/{journalId}/reject
     */
    public function rejectJournal(Request $request, $journalId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500'
        ]);
        
        try {
            $journal = TourJournal::findOrFail($journalId);
            $journal->status = 'rejected';
            $journal->remarks = $request->reason;
            $journal->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Journal rejected',
                'data' => $journal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject journal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get trainee's own tour journals
     * GET /api/tour-journals/my-journals
     */
    public function getMyJournals(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $journals = TourJournal::where('trainee_id', $user->id)
                ->with('tour')
                ->orderBy('created_at', 'desc')
                ->get();
            
            $result = $journals->map(function($journal) {
                return [
                    'id' => (string)$journal->id,
                    'tour_id' => (string)$journal->tour_id,
                    'tour_name' => $journal->tour->name,
                    'tour_dates' => $journal->tour->start_date && $journal->tour->end_date 
                        ? Carbon::parse($journal->tour->start_date)->format('M d, Y') . ' - ' . Carbon::parse($journal->tour->end_date)->format('M d, Y')
                        : 'Dates TBD',
                    'deadline' => $journal->tour->journal_due_date ? Carbon::parse($journal->tour->journal_due_date)->format('Y-m-d') : null,
                    'journal_link' => $journal->journal_link,
                    'status' => $journal->status,
                    'submitted_at' => $journal->submitted_at,
                    'remarks' => $journal->remarks,
                    'can_submit' => $journal->status === 'pending' || $journal->status === 'rejected'
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your journals',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit or update a tour journal
     * POST /api/tour-journals/submit
     */
    public function submitJournal(Request $request): JsonResponse
    {
        $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'journal_link' => 'required|url|max:500'
        ]);
        
        try {
            $user = $request->user();
            
            // Check if enrollment exists
            $enrollment = TourEnrollment::where('tour_id', $request->tour_id)
                ->where('trainee_id', $user->id)
                ->first();
            
            if (!$enrollment) {
                // Auto-enroll if not enrolled
                $enrollment = TourEnrollment::create([
                    'tour_id' => $request->tour_id,
                    'trainee_id' => $user->id,
                    'is_mandatory' => true
                ]);
            }
            
            $journal = TourJournal::updateOrCreate(
                [
                    'tour_id' => $request->tour_id,
                    'trainee_id' => $user->id
                ],
                [
                    'journal_link' => $request->journal_link,
                    'status' => 'submitted',
                    'submitted_at' => now()
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Journal submitted successfully',
                'data' => $journal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit journal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Auto-enroll trainees for a tour (Admin)
     * POST /api/tour-journals/tours/{tourId}/enroll
     */
    public function enrollTrainees($tourId): JsonResponse
    {
        try {
            $tour = Tour::findOrFail($tourId);
            
            // Get all trainees in the batch
            $trainees = User::where('role', 'trainee')
                ->whereHas('batches', function($q) use ($tour) {
                    $q->where('batch_id', $tour->batch_id);
                })->get();
            
            $enrolledCount = 0;
            foreach ($trainees as $trainee) {
                $enrollment = TourEnrollment::firstOrCreate(
                    [
                        'tour_id' => $tourId,
                        'trainee_id' => $trainee->id
                    ],
                    [
                        'is_mandatory' => true
                    ]
                );
                
                if ($enrollment->wasRecentlyCreated) {
                    $enrolledCount++;
                    
                    // Create journal entry for the trainee
                    TourJournal::firstOrCreate(
                        [
                            'tour_id' => $tourId,
                            'trainee_id' => $trainee->id
                        ],
                        [
                            'status' => 'pending'
                        ]
                    );
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => $enrolledCount . ' trainees enrolled successfully',
                'data' => ['enrolled_count' => $enrolledCount]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll trainees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
 * Update an existing journal
 * PUT /api/trainee/tour-journals/{id}
 */
// public function update(Request $request, $id)
// {
//     try {
//         $journal = DB::table('trainee_tour_journals')
//             ->where('id', $id)
//             ->where('trainee_id', Auth::id())
//             ->first();
        
//         if (!$journal) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Journal not found'
//             ], 404);
//         }
        
//         // Check if journal can be updated (only pending or rejected)
//         if (!in_array($journal->status, ['pending', 'rejected'])) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Journal cannot be updated in its current status'
//             ], 400);
//         }
        
//         $updateData = [
//             'title' => $request->title ?? $journal->title,
//             'content' => $request->content,
//             'updated_at' => now()
//         ];
        
//         // Handle file deletion
//         if ($request->delete_file == 'true' && $journal->file_url) {
//             // Delete old file
//             $oldPath = str_replace('/storage/', '', $journal->file_url);
//             if (Storage::disk('public')->exists($oldPath)) {
//                 Storage::disk('public')->delete($oldPath);
//             }
//             $updateData['file_url'] = null;
//             $updateData['file_name'] = null;
//             $updateData['file_size'] = null;
//             $updateData['file_type'] = null;
//         }
        
//         // Handle new file upload
//         if ($request->hasFile('file')) {
//             // Delete old file if exists
//             if ($journal->file_url) {
//                 $oldPath = str_replace('/storage/', '', $journal->file_url);
//                 if (Storage::disk('public')->exists($oldPath)) {
//                     Storage::disk('public')->delete($oldPath);
//                 }
//             }
            
//             $file = $request->file('file');
//             $fileName = time() . '_' . Str::slug($journal->title) . '.' . $file->getClientOriginalExtension();
//             $path = $file->storeAs('tour-journals/' . Auth::id(), $fileName, 'public');
//             $fileUrl = asset('storage/' . $path);
            
//             $updateData['file_url'] = $fileUrl;
//             $updateData['file_name'] = $fileName;
//             $updateData['status'] = 'uploaded';
//         }
        
//         DB::table('trainee_tour_journals')
//             ->where('id', $id)
//             ->update($updateData);
        
//         return response()->json([
//             'success' => true,
//             'message' => 'Journal updated successfully'
//         ]);
        
//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to update journal: ' . $e->getMessage()
//         ], 500);
//     }
// }
}