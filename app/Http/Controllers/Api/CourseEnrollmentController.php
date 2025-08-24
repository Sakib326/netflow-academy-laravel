<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseEnrollmentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/enroll/{slug}",
     *     summary="Enroll a student in a course by slug",
     *     tags={"Enrollment"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Course slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="discount_amount", type="number", format="float", example=10.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Enrollment created or already enrolled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="enrollment_id", type="integer"),
     *             @OA\Property(property="batch_id", type="integer"),
     *             @OA\Property(property="payment_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Course not found"),
     *     @OA\Response(response=400, description="Already enrolled")
     * )
     */
    public function makeEnrollment(Request $request, $slug)
    {
        $user = Auth::user();

        // 1. Check course exists
        $course = Course::where('slug', $slug)->first();
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        }

        // 2. Check if already enrolled

        $existing = Enrollment::where('user_id', $user->id)
            ->whereHas('batch', function ($q) use ($course) {
                $q->where('course_id', $course->id);
            })
            ->orderByDesc('id')
            ->first();


        if ($existing && $existing->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Already enrolled in this course',
                'enrollment_id' => $existing->id,
                'batch_id' => $existing->batch_id,
            ], 400);
        }

        // 3. Find last active batch or create new if needed
        $batch = Batch::where('course_id', $course->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>', now());
            })
            ->orderByDesc('start_date')
            ->first();

        $maxStudents = $batch ? ($batch->max_students ?? null) : null;
        $enrolledCount = $batch ? $batch->enrollments()->count() : 0;

        // If no active batch, or batch is full, create a new batch
        if (!$batch || ($maxStudents && $enrolledCount >= $maxStudents)) {
            $batch = Batch::create([
                'course_id' => $course->id,
                'name' => $course->title . ' Batch ' . (Batch::where('course_id', $course->id)->count() + 1),
                'is_active' => true,
                'start_date' => now(),
            ]);
        }

        // 4. Calculate amount (discount or price)
        $discount = $request->input('discount_amount', 0);
        $amount = $discount > 0 ? max(0, $course->price - $discount) : $course->price;

        // 5. Create enrollment and payment inside transaction
        DB::beginTransaction();
        try {
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'batch_id' => $batch->id,
                'status' => 'pending',
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $amount,
                'status' => 'pending',
                'payment_method' => null,
                'transaction_id' => null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Could not enroll: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Enrollment created. Please complete payment.',
            'enrollment_id' => $enrollment->id,
            'batch_id' => $batch->id,
            'payment_id' => $payment->id,
        ]);
    }
}
