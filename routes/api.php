<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\DashboardController;

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