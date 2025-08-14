<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\UserCourseManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{id}', [CourseController::class, 'show']);
Route::get('/categories', [CourseController::class, 'categories']);
Route::get('/instructors', [CourseController::class, 'instructors']);
Route::get('/reviews', [CourseController::class, 'reviews']);

// Authentication routes with 'auth' prefix
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/update', [AuthController::class, 'update']); // Changed to POST for form data
        Route::post('/update-password', [AuthController::class, 'updatePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-courses', [UserCourseManagementController::class, 'myCourses']);
    Route::post('/submit-assignment', [UserCourseManagementController::class, 'submitAssignment']);
    Route::get('/my-assignments', [UserCourseManagementController::class, 'myAssignments']);
});