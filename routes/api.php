<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Admin\AppointmentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\TraineeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceMonitoringController;
use App\Http\Controllers\Api\LeaveMonitoringController;
use App\Http\Controllers\Api\MemoController; 
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\TraineeFeedbackController;
use App\Http\Controllers\Api\TourJournalController;
use App\Http\Controllers\Api\TourLinkController;
use App\Http\Controllers\Api\TourSubmissionController;
use App\Http\Controllers\Api\TraineeTourJournalController;


//Authentication route
Route::post('/login', [LoginController::class, 'login']);



// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);
    Route::get('/auth/check', [LoginController::class, 'check']);
    
    // Single dashboard endpoint that returns role-specific data
    // Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Role-specific endpoints using role middleware
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/users', [DashboardController::class, 'adminUsers']);
    });

    Route::middleware('role:faculty')->group(function () {
        Route::get('/faculty/courses', [DashboardController::class, 'facultyCourses']);
    });

    Route::middleware('role:trainee')->group(function () {
        Route::get('/trainee/progress', [DashboardController::class, 'traineeProgress']);
    });

    // ========== MEMO ROUTES ==========
    
    // Admin Memo routes (with admin prefix)
    Route::prefix('admin/memos')->middleware('role:admin,course_director')->group(function () {
        Route::get('/', [MemoController::class, 'index']);
        Route::get('/{id}', [MemoController::class, 'show']);
        Route::delete('/{id}', [MemoController::class, 'destroy']);
        Route::post('/{id}/approve', [MemoController::class, 'approve']);
        Route::post('/{id}/reject', [MemoController::class, 'reject']);
        Route::post('/generate/date', [MemoController::class, 'generateForDate']);
        Route::post('/generate-range', [MemoController::class, 'generateForDateRange']);
        Route::post('/generate-trainee', [MemoController::class, 'generateForTrainee']);
        Route::get('/preview', [MemoController::class, 'preview']);
        Route::get('/stats/summary', [MemoController::class, 'statistics']);
    });
    
    // Trainee Memo routes - FIXED: Moved outside the memos prefix and properly defined
    Route::middleware('role:trainee')->group(function () {
        Route::get('/trainee/memos', [MemoController::class, 'getTraineeMemos']);
        Route::get('/memos/my-memos', [MemoController::class, 'getMyMemos']);
    });
    
    // General Memo routes (accessible by authenticated users)
    Route::prefix('memos')->group(function () {
        Route::get('/', [MemoController::class, 'index']);
        Route::get('/{id}', [MemoController::class, 'show']);
        Route::delete('/{id}', [MemoController::class, 'destroy']);
        Route::post('/{id}/approve', [MemoController::class, 'approve']);
        Route::post('/{id}/reject', [MemoController::class, 'reject']);
        Route::get('/stats/summary', [MemoController::class, 'statistics']);
        
        // Generation operations (admin/course_director only)
        Route::middleware('role:admin,course_director')->group(function () {
            Route::post('/generate', [MemoController::class, 'generateForDate']);
            Route::post('/generate-range', [MemoController::class, 'generateForDateRange']);
            Route::post('/generate-trainee', [MemoController::class, 'generateForTrainee']);
            Route::get('/preview', [MemoController::class, 'preview']);
        });
    });
    
    // ========== END MEMO ROUTES ==========

    // Leave routes
    Route::prefix('leaves')->group(function () {
        Route::get('/my-leaves', [LeaveController::class, 'myLeaves']);
        Route::get('/my-balance', [LeaveController::class, 'myBalance']);
        Route::post('/', [LeaveController::class, 'store']);
        Route::delete('/{id}/cancel', [LeaveController::class, 'cancel']);
        Route::get('/stats', [LeaveController::class, 'stats']);
        
        Route::middleware('role:admin,course_director')->group(function () {
            Route::get('/', [LeaveController::class, 'index']);
            Route::patch('/{id}/approve', [LeaveController::class, 'approve']);
            Route::patch('/{id}/reject', [LeaveController::class, 'reject']);
        });
    });
});

// Course management routes (outside auth group but with auth middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::post('/', [CourseController::class, 'store']);
        Route::get('/{id}', [CourseController::class, 'show']);
        Route::put('/{id}', [CourseController::class, 'update']);
        Route::delete('/{id}', [CourseController::class, 'destroy']);
        Route::patch('/{id}/status', [CourseController::class, 'updateStatus']);
        Route::patch('/bulk-status', [CourseController::class, 'bulkUpdateStatus']);
        Route::get('/status/{status}', [CourseController::class, 'getByStatus']);
    });

    // Batch routes
    Route::prefix('batches')->group(function () {
        Route::get('/', [BatchController::class, 'index']);
        Route::get('/available-courses', [BatchController::class, 'getAvailableCourses']);
        Route::get('/trainees', [BatchController::class, 'getTrainees']);
        Route::post('/', [BatchController::class, 'store']);
        Route::get('/{id}', [BatchController::class, 'show']);
        Route::get('/{id}/with-trainees', [BatchController::class, 'getBatchWithTrainees']);
        Route::put('/{id}', [BatchController::class, 'update']);
        Route::patch('/{id}/status', [BatchController::class, 'updateStatus']);
        Route::post('/{id}/assign', [BatchController::class, 'assignTrainees']);
        Route::post('/{id}/remove', [BatchController::class, 'removeTrainees']);
        Route::delete('/{id}', [BatchController::class, 'destroy']);
    });

    // Timetable routes
    Route::prefix('timetable')->group(function () {
        Route::get('/', [TimetableController::class, 'index']);
        Route::get('/grid', [TimetableController::class, 'getGrid']);
        Route::get('/faculty', [TimetableController::class, 'getFacultyList']);
        Route::get('/subjects', [TimetableController::class, 'getSubjectList']);
        Route::get('/day/{day}', [TimetableController::class, 'getByDay']);
        Route::get('/faculty/{faculty}', [TimetableController::class, 'getByFaculty']);
        Route::post('/', [TimetableController::class, 'store']);
        Route::get('/{id}', [TimetableController::class, 'show']);
        Route::put('/{id}', [TimetableController::class, 'update']);
        Route::patch('/{id}/substitute', [TimetableController::class, 'substituteFaculty']);
        Route::patch('/{id}/revert', [TimetableController::class, 'revertSubstitution']);
        Route::delete('/{id}', [TimetableController::class, 'destroy']);
    });

    // Tour routes
    Route::prefix('tours')->group(function () {
        Route::get('/', [TourController::class, 'index']);
        Route::get('/completed', [TourController::class, 'getCompletedTours'])
            ->middleware('role:admin,course_clerk');
        Route::get('/stats', [TourController::class, 'stats']);
        Route::get('/faculty', [TourController::class, 'getFacultyList']);
        Route::get('/batches', [TourController::class, 'getBatchesList']);
        Route::get('/batches/{batchId}/trainees', [TourController::class, 'getBatchTrainees']);
        Route::post('/', [TourController::class, 'store']);
        Route::get('/{id}', [TourController::class, 'show']);
        Route::put('/{id}', [TourController::class, 'update']);
        Route::delete('/{id}', [TourController::class, 'destroy']);

    });

    // Faculty routes
    Route::prefix('faculty')->group(function () {
        Route::get('/', [FacultyController::class, 'index']);
        Route::get('/stats', [FacultyController::class, 'stats']);
        Route::get('/courses', [FacultyController::class, 'getCourses']);
        Route::post('/', [FacultyController::class, 'store']);
        Route::get('/{id}', [FacultyController::class, 'show']);
        Route::put('/{id}', [FacultyController::class, 'update']);
        Route::delete('/{id}', [FacultyController::class, 'destroy']);
    });

    // Trainee routes
    Route::prefix('trainees')->group(function () {
        Route::get('/', [TraineeController::class, 'index']);
        Route::get('/stats', [TraineeController::class, 'stats']);
        Route::get('/batches', [TraineeController::class, 'getBatches']);
        Route::post('/', [TraineeController::class, 'store']);
        Route::get('/{id}', [TraineeController::class, 'show']);
        Route::put('/{id}', [TraineeController::class, 'update']);
        Route::delete('/{id}', [TraineeController::class, 'destroy']);
    });

    // Attendance routes
    Route::prefix('attendance')->group(function () {
        Route::get('/today', [AttendanceController::class, 'getTodaySessions']);
        Route::get('/history', [AttendanceController::class, 'getHistory']);
        Route::get('/stats', [AttendanceController::class, 'getStats']);
        Route::get('/disputes', [AttendanceController::class, 'getDisputes']);
        Route::post('/mark', [AttendanceController::class, 'markAttendance']);
        Route::post('/dispute', [AttendanceController::class, 'raiseDispute']);
    });
});

// Monitoring routes (admin/course_director only)
Route::middleware(['auth:sanctum', 'role:admin,course_director'])->prefix('monitoring')->group(function () {
    Route::get('/batches', [AttendanceMonitoringController::class, 'getBatches']);
    Route::get('/batches/{batchId}/trainees', [AttendanceMonitoringController::class, 'getTraineesByBatch']);
    Route::get('/trainees/{traineeId}/attendance', [AttendanceMonitoringController::class, 'getTraineeAttendance']);
    Route::get('/stats/global', [AttendanceMonitoringController::class, 'getGlobalStats']);
    Route::patch('/attendance/{attendanceId}', [AttendanceMonitoringController::class, 'updateAttendance']);
    Route::get('/export', [AttendanceMonitoringController::class, 'exportReport']);
});

Route::middleware(['auth:sanctum', 'role:admin,course_director'])->prefix('leave-monitoring')->group(function () {
    Route::get('/batches', [LeaveMonitoringController::class, 'getBatches']);
    Route::get('/batches/{batchId}/trainees', [LeaveMonitoringController::class, 'getTraineesByBatch']);
    Route::get('/trainees/{traineeId}/leaves', [LeaveMonitoringController::class, 'getTraineeLeaves']);
    Route::patch('/leaves/{leaveId}/approve', [LeaveMonitoringController::class, 'approveLeave']);
    Route::patch('/leaves/{leaveId}/reject', [LeaveMonitoringController::class, 'rejectLeave']);
    Route::get('/stats', [LeaveMonitoringController::class, 'getStats']);
    Route::get('/export', [LeaveMonitoringController::class, 'exportReport']);
});

// Course Director Appointment Routes (Admin Only)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/faculty/eligible', [AppointmentController::class, 'getEligibleFaculty']);
    Route::get('/current-director', [AppointmentController::class, 'getCurrentDirector']);
    Route::get('/appointment-history', [AppointmentController::class, 'getAppointmentHistory']);
    Route::get('/appointment-stats', [AppointmentController::class, 'getAppointmentStats']);
    Route::post('/appoint', [AppointmentController::class, 'appoint']);
    Route::post('/extend-term', [AppointmentController::class, 'extendTerm']);
});


Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/feedback')->group(function () {
    // Faculty feedback routes
    Route::get('/faculty-subjects', [FeedbackController::class, 'getFacultySubjects']);
    Route::post('/unlock', [FeedbackController::class, 'unlockFeedback']);
    Route::get('/analytics', [FeedbackController::class, 'getFacultyAnalytics']);
    Route::get('/summary', [FeedbackController::class, 'getFeedbackSummary']); // Add this line
    Route::patch('/syllabus-status', [FeedbackController::class, 'updateSyllabusStatus']);
    
    // Final feedback routes
    Route::get('/final-monitoring', [FeedbackController::class, 'getFinalMonitoring']);
    Route::post('/release-final', [FeedbackController::class, 'releaseFinalFeedback']);
});

// Faculty Feedback Routes (Trainee)
Route::middleware('auth:sanctum')->prefix('admin/feedback')->group(function () {
    // Trainee routes
    Route::middleware('role:trainee')->group(function () {
        Route::get('/faculty/assignments', [FeedbackController::class, 'getTraineeFacultyAssignments']);
        Route::post('/faculty/submit', [FeedbackController::class, 'submitFacultyFeedback']);
        Route::get('/final/assignment', [FeedbackController::class, 'getTraineeFinalAssignment']);
        Route::get('/final/response', [FeedbackController::class, 'getTraineeFinalResponse']);
        Route::post('/final/save-section', [FeedbackController::class, 'saveFinalSection']);
        Route::post('/final/submit', [FeedbackController::class, 'submitFinalFeedbackTrainee']);
    });
    
    // Faculty view routes
    Route::middleware('role:faculty')->group(function () {
        Route::get('/faculty/results', [FeedbackController::class, 'getFacultyResults']);
    });
});


// In routes/api.php, inside the auth:sanctum middleware group

// Trainee Feedback Routes
Route::middleware(['auth:sanctum', 'role:trainee'])->prefix('feedback')->group(function () {
    Route::get('/faculty/assignments', [TraineeFeedbackController::class, 'getFacultyAssignments']);
    Route::post('/faculty/submit', [TraineeFeedbackController::class, 'submitFacultyFeedback']);
    Route::get('/final/assignment', [TraineeFeedbackController::class, 'getFinalAssignment']);
    Route::get('/final/response', [TraineeFeedbackController::class, 'getFinalResponse']);
    Route::post('/final/save-section', [TraineeFeedbackController::class, 'saveFinalSection']);
    Route::post('/final/submit', [TraineeFeedbackController::class, 'submitFinalFeedback']);
});


// ========== TOUR JOURNAL ROUTES ==========
// Admin routes for tour journals (NO creation here - use existing TourController)
Route::middleware(['auth:sanctum', 'role:admin,course_director'])->prefix('tour-journals')->group(function () {
    Route::get('/batches', [TourJournalController::class, 'getBatches']);
    Route::get('/batches/{batchId}/tours', [TourJournalController::class, 'getBatchTours']);
    Route::get('/monitoring', [TourJournalController::class, 'getMonitoring']);
    Route::get('/tours/{tourId}/details', [TourJournalController::class, 'getTourDetails']);
    Route::post('/tours/{tourId}/extend-deadline', [TourJournalController::class, 'extendDeadline']);
    Route::post('/journals/{journalId}/approve', [TourJournalController::class, 'approveJournal']);
    Route::post('/journals/{journalId}/reject', [TourJournalController::class, 'rejectJournal']);
    Route::post('/tours/{tourId}/enroll', [TourJournalController::class, 'enrollTrainees']);
});

// Trainee routes for tour journals
Route::middleware(['auth:sanctum', 'role:trainee'])->prefix('tour-journals')->group(function () {
    Route::get('/my-journals', [TourJournalController::class, 'getMyJournals']);
    Route::post('/submit', [TourJournalController::class, 'submitJournal']);
});

// Tour Support Routes with proper middleware
Route::prefix('tour-support')->group(function () {
    
    // Tour Links - Course Clerk and Admin only
    Route::middleware(['auth:sanctum', 'role:course_clerk,admin'])->group(function () {
        Route::get('links', [TourLinkController::class, 'index']);
        Route::get('links/batches', [TourLinkController::class, 'getBatches']);
        Route::post('links', [TourLinkController::class, 'store']);
        Route::get('links/{id}', [TourLinkController::class, 'show']);
        Route::put('links/{id}', [TourLinkController::class, 'update']);
        Route::delete('links/{id}', [TourLinkController::class, 'destroy']);
        Route::get('links/{id}/submissions', [TourLinkController::class, 'getSubmissions']);
        
    });
    
    // Public submission endpoint (no auth required, uses link_id)
    Route::post('submit/{linkId}', [TourLinkController::class, 'submitJournal']);
    
    // Submissions - Mixed access based on role
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('submissions', [TourSubmissionController::class, 'index']);
        Route::get('submissions/statistics', [TourSubmissionController::class, 'statistics']);
        Route::get('submissions/{id}', [TourSubmissionController::class, 'show']);
        Route::get('submissions/{id}/download', [TourSubmissionController::class, 'download']);
        
        // Course Clerk only operations
        Route::middleware(['role:course_clerk,admin'])->group(function () {
            Route::post('submissions/{id}/mark-stored', [TourSubmissionController::class, 'markAsStored']);
            Route::post('submissions/{id}/approve', [TourSubmissionController::class, 'approve']);
            Route::post('submissions/{id}/reject', [TourSubmissionController::class, 'reject']);
        });
    });
});


// Trainee Tour Journal Routes (with proper middleware)
// Route::middleware(['auth:sanctum', 'role:trainee'])->prefix('trainee/tour-journals')->group(function () {
//     Route::get('/', [TraineeTourJournalController::class, 'index']);
//     Route::get('/available-tours', [TraineeTourJournalController::class, 'getAvailableTours']);
//     Route::get('/statistics', [TraineeTourJournalController::class, 'statistics']);
//     Route::post('/', [TraineeTourJournalController::class, 'store']);
//     Route::get('/{id}', [TraineeTourJournalController::class, 'show']);
//     // Route::put('/{id}', [TraineeTourJournalController::class, 'update']);
//     // Route::delete('/{id}', [TraineeTourJournalController::class, 'destroy']);
//         Route::post('/{id}', [TraineeTourJournalController::class, 'update']); // For POST with _method=PUT
//     // OR use PUT method:
//     // Route::put('/{id}', [TraineeTourJournalController::class, 'update']);
//     Route::delete('/{id}', [TraineeTourJournalController::class, 'destroy']);
// });

Route::middleware(['auth:sanctum', 'role:trainee'])->prefix('trainee/tour-journals')->group(function () {
    // Static routes first
    Route::get('/available-tours', [TraineeTourJournalController::class, 'getAvailableTours']);
    Route::get('/statistics', [TraineeTourJournalController::class, 'statistics']);
    
    // Then resource-like routes
    Route::get('/', [TraineeTourJournalController::class, 'index']);
    Route::post('/', [TraineeTourJournalController::class, 'store']);
    
    // Dynamic routes with parameters LAST
    Route::get('/{id}', [TraineeTourJournalController::class, 'show']);
    // Route::post('/{id}', [TraineeTourJournalController::class, 'update']);
      // Allow both PUT and POST with _method for update
    Route::put('/{id}', [TraineeTourJournalController::class, 'update']);
    Route::match(['put', 'patch', 'post'], '/{id}', [TraineeTourJournalController::class, 'update']);
    Route::delete('/{id}', [TraineeTourJournalController::class, 'destroy']);
});
