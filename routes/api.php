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
use App\Http\Controllers\Api\ZoomController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CertificateController;

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


Route::get('/coupons/check/{slug}', [CouponController::class, 'check']);



Route::get('zoom/latest', [ZoomController::class, 'getLatest'])->name('zoom.latest');

Route::middleware('auth:sanctum')->group(function () {

    // Route::get('zoom/latest', [ZoomController::class, 'getLatest'])->name('zoom.latest');

    Route::get('/my-courses', [UserCourseManagementController::class, 'myCourses']);
    Route::get('/my-courses/status-count', [UserCourseManagementController::class, 'myCoursesStatusCount']);
    Route::post('/enroll/{slug}', [CourseEnrollmentController::class, 'makeEnrollment']);


});


// ===========================================
// 🔥 ROBUST DISCUSSION ROUTES
// ===========================================

// Debug route (remove in production)
Route::get('debug-discussions', function () {
    try {
        $totalDiscussions = \App\Models\Discussion::count();
        $courseDiscussions = \App\Models\Discussion::where('discussable_type', \App\Models\Course::class)->count();
        $parentThreads = \App\Models\Discussion::whereNull('parent_id')->count();

        $sampleDiscussion = \App\Models\Discussion::with('user')
            ->where('discussable_type', \App\Models\Course::class)
            ->whereNull('parent_id')
            ->first();

        return response()->json([
            'debug_info' => [
                'total_discussions' => $totalDiscussions,
                'course_discussions' => $courseDiscussions,
                'parent_threads' => $parentThreads,
                'total_courses' => \App\Models\Course::count(),
                'total_users' => \App\Models\User::count(),
            ],
            'sample_discussion' => $sampleDiscussion ? [
                'id' => $sampleDiscussion->id,
                'title' => $sampleDiscussion->title,
                'content' => substr($sampleDiscussion->content, 0, 100) . '...',
                'user_name' => $sampleDiscussion->user->name ?? 'Unknown',
                'discussable_type' => $sampleDiscussion->discussable_type,
                'discussable_id' => $sampleDiscussion->discussable_id,
                'created_at' => $sampleDiscussion->created_at,
            ] : null,
            'environment' => app()->environment(),
            'database' => config('database.default'),
            'timestamp' => now()->toISOString(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Certificate routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/certificates/my-certificates', [CertificateController::class, 'getMyCertificates'])->name('certificates.my');
    Route::get('/certificates/{id}', [CertificateController::class, 'show'])->name('certificates.show');
});


// Main discussion routes - flexible and robust
Route::prefix('discussions')->name('discussions.')->group(function () {

    // Public routes
    Route::get('/', [DIscussionController::class, 'index'])->name('index');
    Route::get('/{discussion}', [DIscussionController::class, 'show'])
        ->where('discussion', '[0-9]+')
        ->name('show');

    // Protected routes requiring authentication
    Route::middleware('auth:sanctum')->group(function () {

        // CRUD operations
        Route::post('/', [DIscussionController::class, 'store'])->name('store');
        Route::put('/{discussion}', [DIscussionController::class, 'update'])
            ->where('discussion', '[0-9]+')
            ->name('update');
        Route::delete('/{discussion}', [DIscussionController::class, 'destroy'])
            ->where('discussion', '[0-9]+')
            ->name('destroy');

        // Discussion actions
        Route::post('/{discussion}/reply', [DIscussionController::class, 'reply'])
            ->where('discussion', '[0-9]+')
            ->name('reply');
        Route::post('/{discussion}/upvote', [DIscussionController::class, 'upvote'])
            ->where('discussion', '[0-9]+')
            ->name('upvote');
        Route::post('/{discussion}/mark-answered', [DIscussionController::class, 'markAnswered'])
            ->where('discussion', '[0-9]+')
            ->name('mark_answered');
    });
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
