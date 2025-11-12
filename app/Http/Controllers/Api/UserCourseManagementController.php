<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Carbon\Carbon;

class UserCourseManagementController extends Controller
{
    #[OA\Schema(
        schema: "EnrolledCourse",
        type: "object",
        properties: [
            new OA\Property(property: "enrollment_id", type: "integer", example: 1),
            new OA\Property(property: "enrollment_date", type: "string", format: "date-time"),
            new OA\Property(property: "enrollment_status", type: "string", example: "active"),
            new OA\Property(property: "progress_percentage", type: "number", format: "float", example: 75.5),
            new OA\Property(property: "completed_lessons", type: "integer", example: 10),
            new OA\Property(property: "total_lessons", type: "integer", example: 20),
            new OA\Property(property: "batch", type: "object"),
            new OA\Property(
                property: "course",
                type: "object",
                properties: [
                    new OA\Property(property: "id", type: "integer", example: 1),
                    new OA\Property(property: "title", type: "string"),
                    new OA\Property(property: "slug", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "short_description", type: "string"),
                    new OA\Property(property: "thumbnail", type: "string", nullable: true),
                    new OA\Property(property: "duration", type: "string"),
                    new OA\Property(property: "level", type: "string"),
                    new OA\Property(property: "language", type: "string"),
                    new OA\Property(property: "status", type: "string"),
                    new OA\Property(property: "category", type: "object"),
                    new OA\Property(property: "instructor", type: "object"),
                ]
            ),
            new OA\Property(property: "next_class_time", type: "string", format: "date-time", nullable: true),
        ]
    )]

    #[OA\Get(
        path: "/api/my-courses",
        summary: "Get user's enrolled courses",
        description: "Retrieve all courses the authenticated user is enrolled in with pagination support",
        tags: ["User Courses"],
        parameters: [
          new OA\Parameter(
              name: "per_page",
              description: "Number of records per page (max 20)",
              in: "query",
              required: false,
              schema: new OA\Schema(type: "integer", default: 10)
          ),
          new OA\Parameter(
              name: "page",
              description: "Page number",
              in: "query",
              required: false,
              schema: new OA\Schema(type: "integer", default: 1)
          ),
          new OA\Parameter(
              name: "status",
              description: "Filter by enrollment status",
              in: "query",
              required: false,
              schema: new OA\Schema(
                  type: "string",
                  enum: ["active", "completed", "dropped", "suspended", "pending"]
              )
          ),
        ],
        security: [["sanctum" => []]],
        responses: [
          new OA\Response(
              response: 200,
              description: "Successfully retrieved enrolled courses",
              content: new OA\JsonContent(
                  type: "object",
                  properties: [
                      new OA\Property(
                          property: "data",
                          type: "array",
                          items: new OA\Items(ref: "#/components/schemas/EnrolledCourse")
                      ),
                      new OA\Property(
                          property: "links",
                          type: "object",
                          properties: [
                              new OA\Property(property: "first", type: "string", example: "http://example.com/api/my-courses?page=1"),
                              new OA\Property(property: "last", type: "string", example: "http://example.com/api/my-courses?page=5"),
                              new OA\Property(property: "prev", type: "string", nullable: true),
                              new OA\Property(property: "next", type: "string", nullable: true),
                          ]
                      ),
                      new OA\Property(
                          property: "meta",
                          type: "object",
                          properties: [
                              new OA\Property(property: "current_page", type: "integer", example: 1),
                              new OA\Property(property: "from", type: "integer", example: 1),
                              new OA\Property(property: "last_page", type: "integer", example: 5),
                              new OA\Property(property: "per_page", type: "integer", example: 10),
                              new OA\Property(property: "to", type: "integer", example: 10),
                              new OA\Property(property: "total", type: "integer", example: 50),
                          ]
                      ),
                  ]
              )
          ),
          new OA\Response(
              response: 401,
              description: "Unauthenticated"
          ),
        ]
    )]

    public function myCourses(Request $request)
    {
        $user = $request->user();
        $perPage = min($request->get('per_page', 10), 20);

        $query = Enrollment::with([
            'batch',
            'batch.classRoutine', // ✅ ADD THIS
            'course.category',
            'course.instructor',
            'user'
        ])->where('user_id', $user->id);

        // Filter by enrollment status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->paginate($perPage);

        $enrollments->getCollection()->transform(function ($enrollment) use ($user) {
            $course = $enrollment->course;
            $batch = $enrollment->batch;

            // Calculate course progress
            $totalLessons = $course->modules->sum(function ($module) {
                return $module->lessons->count();
            });

            $completedLessons = 0;
            if ($totalLessons > 0) {
                foreach ($course->modules as $module) {
                    foreach ($module->lessons as $lesson) {
                        // $completedLessons += $lesson->isCompletedByUser($user->id) ? 1 : 0;
                    }
                }
            }

            $progressPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

            // Check batch status
            $batchStatus = 'active';
            $now = Carbon::now();

            if ($batch) {
                if ($batch->start_date && Carbon::parse($batch->start_date)->isFuture()) {
                    $batchStatus = 'upcoming';
                } elseif ($batch->end_date && Carbon::parse($batch->end_date)->isPast()) {
                    $batchStatus = 'expired';
                }
            }

            // ✅ Build class routine data
            $classRoutine = null;
            if ($batch && $batch->classRoutine) {
                $classRoutine = [
                    'id' => $batch->classRoutine->id,
                    'days' => $batch->classRoutine->days,
                    'off_dates' => $batch->classRoutine->off_dates,
                    'is_class_today' => $batch->classRoutine->isClassToday(),
                    'is_off_today' => $batch->classRoutine->isOffToday(),
                    'today_class_time' => $batch->classRoutine->getTodayClassTime(),
                ];
            }

            return [
                'enrollment_id' => $enrollment->id,
                'enrollment_date' => $enrollment->created_at,
                'enrollment_status' => $enrollment->status,
                'progress_percentage' => $progressPercentage,
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'batch' => $batch ? [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'zoom_link' => $batch->zoom_link,
                    'start_date' => $batch->start_date,
                    'end_date' => $batch->end_date,
                    'status' => $batchStatus,
                    'max_students' => $batch->max_students ?? null,
                    'current_students' => $batch->enrollments()->count(),
                    'schedule' => $batch->schedule ?? null,
                    'timezone' => $batch->timezone ?? 'UTC',
                    'days_until_start' => $batch->start_date ? Carbon::parse($batch->start_date)->diffInDays($now, false) : null,
                    'days_until_end' => $batch->end_date ? Carbon::parse($batch->end_date)->diffInDays($now, false) : null,
                    'class_routine' => $classRoutine, // ✅ ADD THIS
                ] : null,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'short_description' => $course->short_description,
                    'thumbnail' => $course->thumbnail ? asset('storage/' . $course->thumbnail) : null,
                    'duration' => $course->duration,
                    'level' => $course->level,
                    'language' => $course->language,
                    'status' => $course->status,
                    'category' => $course->category ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name,
                        'slug' => $course->category->slug,
                    ] : null,
                    'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'name' => $course->instructor->name,
                        'email' => $course->instructor->email,
                        'avatar' => $course->instructor->avatar ? asset('storage/' . $course->instructor->avatar) : null,
                        'bio' => $course->instructor->bio ?? '',
                        'designation' => $course->instructor->designation ?? '',
                    ] : null,
                ],
                'next_class_time' => $batch && $batch->schedule ? $this->calculateNextClassTime($batch) : null,
            ];
        });

        return response()->json($enrollments);
    }

    private function calculateNextClassTime($batch)
    {
        // TODO: Implement your next class calculation logic here
        return null;
    }

    /**
    * @OA\Get(
    *     path="/api/my-courses/status-count",
    *     summary="Get count of enrollments by status for the current user",
    *     tags={"User Courses"},
    *     security={{"sanctum":{}}},
    *     @OA\Response(
    *         response=200,
    *         description="Status wise enrollment count",
    *         @OA\JsonContent(
    *             @OA\Property(property="active", type="integer", example=2),
    *             @OA\Property(property="completed", type="integer", example=1),
    *             @OA\Property(property="expired", type="integer", example=0),
    *             @OA\Property(property="pending", type="integer", example=0)
    *         )
    *     )
    * )
    */
    public function myCoursesStatusCount(Request $request)
    {
        $user = $request->user();

        $counts = Enrollment::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Ensure all statuses are present in the response
        $allStatuses = ['active', 'completed', 'dropped', 'suspended', 'pending'];
        $result = [];
        foreach ($allStatuses as $status) {
            $result[$status] = (int)($counts[$status] ?? 0);
        }

        return response()->json($result);
    }
}
