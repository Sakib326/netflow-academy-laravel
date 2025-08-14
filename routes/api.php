<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\UserCourseManagementController;
use App\Http\Controllers\Api\DIscussionController;
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

Route::prefix('courses/{course_id}')->group(function () {
    Route::get('discussions', [DIscussionController::class, 'index']);
    Route::post('discussions', [DIscussionController::class, 'store'])->middleware('auth:sanctum');
});

Route::prefix('discussions')->middleware('auth:sanctum')->group(function () {
    Route::get('{id}', [DIscussionController::class, 'show'])->withoutMiddleware('auth:sanctum');
    Route::put('{id}', [DIscussionController::class, 'update']);
    Route::delete('{id}', [DIscussionController::class, 'destroy']);
    Route::post('{id}/reply', [DIscussionController::class, 'reply']);
    Route::post('{id}/upvote', [DIscussionController::class, 'upvote']);
    Route::post('{id}/mark-answered', [DIscussionController::class, 'markAnswered']);
});