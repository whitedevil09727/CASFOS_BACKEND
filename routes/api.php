<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Admin\AppointmentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\BatchController;

Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});

// Public routes
Route::post('/login', [LoginController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);
    
    // Single dashboard endpoint that returns role-specific data
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
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
});


Route::prefix('courses')->group(function () {
    // Standard CRUD routes
    Route::get('/', [CourseController::class, 'index']);           // GET /api/courses
    Route::post('/', [CourseController::class, 'store']);          // POST /api/courses
    Route::get('/{id}', [CourseController::class, 'show']);        // GET /api/courses/{id}
    Route::put('/{id}', [CourseController::class, 'update']);      // PUT /api/courses/{id}
    Route::delete('/{id}', [CourseController::class, 'destroy']);  // DELETE /api/courses/{id}
    
    // Special status routes
    Route::patch('/{id}/status', [CourseController::class, 'updateStatus']);     // PATCH /api/courses/{id}/status
    Route::patch('/bulk-status', [CourseController::class, 'bulkUpdateStatus']); // PATCH /api/courses/bulk-status
    Route::get('/status/{status}', [CourseController::class, 'getByStatus']);    // GET /api/courses/status/Published
});


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



// ─── Course Director Appointment Routes (Admin Only) ─────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Get eligible faculty members who can be appointed as Course Director
    Route::get('/faculty/eligible', [AppointmentController::class, 'getEligibleFaculty']);
    
    // Get current Course Director
    Route::get('/current-director', [AppointmentController::class, 'getCurrentDirector']);
    
    // Get appointment history (all past Course Directors)
    Route::get('/appointment-history', [AppointmentController::class, 'getAppointmentHistory']);
    
    // Get appointment statistics
    Route::get('/appointment-stats', [AppointmentController::class, 'getAppointmentStats']);
    
    // Appoint a new Course Director (automatically demotes existing one)
    Route::post('/appoint', [AppointmentController::class, 'appoint']);
    
    // Extend term for current Course Director
    Route::post('/extend-term', [AppointmentController::class, 'extendTerm']);
});