<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\UserCourseManagementController;
use App\Http\Controllers\Api\CourseEnrollmentController;
use App\Http\Controllers\Api\LessonModuleController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\DIscussionController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);
Route::get('/categories', [CourseController::class, 'categories']);
Route::get('/instructors', [CourseController::class, 'instructors']);
Route::get('/reviews', [CourseController::class, 'reviews']);

// Approve (mark pending -> paid)
Route::post('orders/{id}/approve', [OrderController::class, 'approveOrder'])
    ->name('orders.approve');

// Get order by numeric id
Route::get('orders/id/{id}', [OrderController::class, 'getOrderById'])
    ->name('orders.showById');


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-courses', [UserCourseManagementController::class, 'myCourses']);
    Route::get('/my-courses/status-count', [UserCourseManagementController::class, 'myCoursesStatusCount']);
    Route::post('/enroll/{slug}', [CourseEnrollmentController::class, 'makeEnrollment']);


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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/courses/{course_id}/batches/{batch_id}/exams', [ExamController::class, 'getCourseExams']);
    Route::post('/exams/{exam_id}/start', [ExamController::class, 'startExam']);
    Route::post('/exams/{exam_id}/finish', [ExamController::class, 'finishExam']);
    Route::get('/exams/{exam_id}/result', [ExamController::class, 'getExamResult']);
    Route::get('/exams/{exam_id}/result/details', [ExamController::class, 'getExamResultDetails']);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/modules/{slug}', [LessonModuleController::class, 'modulesBySlug']);
    Route::get('/lessons/{slug}', [LessonModuleController::class, 'lessonBySlug']);
    Route::post('/lessons/{slug}/submit', [LessonModuleController::class, 'submitBySlug']);
    Route::get('/lessons/{slug}/submissions', [LessonModuleController::class, 'submissionsBySlug']);



    Route::post('/orders/create/{slug}', [OrderController::class, 'createOrder']);
    Route::get('/orders/my-orders', [OrderController::class, 'getMyOrders']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'getOrderDetails']);
    Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancelOrder']);
    Route::get('/orders/stats', [OrderController::class, 'getOrderStats']);

});
