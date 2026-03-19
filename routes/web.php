<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'Laravel is working',
        'message' => 'Welcome route is accessible'
    ]);
});

Route::get('/test-simple', function () {
    return response()->json([
        'success' => true,
        'message' => 'Simple test route works!',
        'time' => now()->toDateTimeString()
    ]);
});