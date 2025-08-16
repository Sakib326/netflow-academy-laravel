<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\Assignment;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Carbon\Carbon;

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
        new OA\Property(property: "course", type: "object"),
        new OA\Property(property: "next_class_time", type: "string", format: "date-time", nullable: true),
    ]
)]

class UserCourseManagementController extends Controller
{
    #[OA\Get(
        path: "/api/my-courses",
        summary: "Get enrolled courses for logged-in user",
        description: "Returns all courses the user is enrolled in, with batch, course, modules, lessons, instructor details and progress tracking",
        tags: ["User Courses"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                description: "Filter by enrollment status",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["active", "completed", "expired", "suspended"])
            ),
            new OA\Parameter(
                name: "page",
                description: "Page number",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/EnrolledCourse")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "last_page", type: "integer", example: 3),
                        new OA\Property(property: "total", type: "integer", example: 25)
                    ]
                )
            )
        ]
    )]
    public function myCourses(Request $request)
    {
        $user = $request->user();
        $perPage = min($request->get('per_page', 10), 20);

        $query = Enrollment::with([
            'batch',
            'course.category',
            'course.instructor',
            'course.modules' => function($q) {
                $q->orderBy('order_index');
            },
            'course.modules.lessons' => function($q) {
                $q->orderBy('order_index');
            },
            'course.modules.lessons.assignments',
            'user'
        ])
        ->where('user_id', $user->id);

        // Filter by enrollment status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $enrollments = $query->paginate($perPage);

        $enrollments->getCollection()->transform(function($enrollment) use ($user) {
            $course = $enrollment->course;
            $batch = $enrollment->batch;
            
            // Calculate course progress
            $totalLessons = $course->modules->sum(function($module) {
                return $module->lessons->count();
            });
            
            // Get completed lessons for this user
            $completedLessons = 0;
            if ($totalLessons > 0) {
                foreach ($course->modules as $module) {
                    foreach ($module->lessons as $lesson) {
                        // Check if user has completed this lesson (you might have a user_lesson_progress table)
                        // For now, we'll assume completion is tracked elsewhere
                        // $completedLessons += $lesson->isCompletedByUser($user->id) ? 1 : 0;
                    }
                }
            }
            
            $progressPercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

            // Check if batch is active, expired, or upcoming
            $batchStatus = 'active';
            $now = Carbon::now();
            
            if ($batch) {
                if ($batch->start_date && Carbon::parse($batch->start_date)->isFuture()) {
                    $batchStatus = 'upcoming';
                } elseif ($batch->end_date && Carbon::parse($batch->end_date)->isPast()) {
                    $batchStatus = 'expired';
                }
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
                    'start_date' => $batch->start_date,
                    'end_date' => $batch->end_date,
                    'status' => $batchStatus,
                    'max_students' => $batch->max_students ?? null,
                    'current_students' => $batch->enrollments()->count(),
                    'schedule' => $batch->schedule ?? null,
                    'timezone' => $batch->timezone ?? 'UTC',
                    'days_until_start' => $batch->start_date ? Carbon::parse($batch->start_date)->diffInDays($now, false) : null,
                    'days_until_end' => $batch->end_date ? Carbon::parse($batch->end_date)->diffInDays($now, false) : null,
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
                    'modules' => $course->modules->map(function($module) use ($user) {
                        return [
                            'id' => $module->id,
                            'title' => $module->title,
                            'description' => $module->description,
                            'order' => $module->order_index,
                            'duration' => $module->duration ?? null,
                            'lessons_count' => $module->lessons->count(),
                            'lessons' => $module->lessons->map(function($lesson) use ($user) {
                                $hasAssignments = $lesson->assignments && $lesson->assignments->count() > 0;
                                $submittedAssignments = 0;
                                
                                if ($hasAssignments) {
                                    foreach ($lesson->assignments as $assignment) {
                                        $submission = Submission::where('user_id', $user->id)
                                            ->where('assignment_id', $assignment->id)
                                            ->first();
                                        if ($submission) {
                                            $submittedAssignments++;
                                        }
                                    }
                                }

                                return [
                                    'id' => $lesson->id,
                                    'title' => $lesson->title,
                                    'description' => $lesson->description,
                                    'order' => $lesson->order_index,
                                    'duration' => $lesson->duration,
                                    'lesson_type' => $lesson->lesson_type,
                                    'is_free' => $lesson->is_free,
                                    'video_url' => $lesson->video_url,
                                    'content' => $lesson->content,
                                    'has_assignments' => $hasAssignments,
                                    'assignments_count' => $lesson->assignments ? $lesson->assignments->count() : 0,
                                    'submitted_assignments' => $submittedAssignments,
                                    'assignment_completion_rate' => $hasAssignments && $lesson->assignments->count() > 0 
                                        ? round(($submittedAssignments / $lesson->assignments->count()) * 100, 1) 
                                        : 0,
                                ];
                            }),
                        ];
                    }),
                ],
                'next_class_time' => $batch && $batch->schedule ? $this->calculateNextClassTime($batch) : null,
            ];
        });

        return response()->json($enrollments);
    }

    #[OA\Post(
        path: "/api/submit-assignment",
        summary: "Submit or update assignment",
        description: "Submit an assignment for a course. Validates enrollment, batch timing, and assignment deadlines.",
        tags: ["User Courses"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["assignment_id"],
                    properties: [
                        new OA\Property(property: "assignment_id", type: "integer", example: 1),
                        new OA\Property(property: "content", type: "string", example: "My assignment solution"),
                        new OA\Property(property: "file", type: "string", format: "binary"),
                        new OA\Property(property: "notes", type: "string", example: "Additional notes"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Submission successful"
            ),
            new OA\Response(
                response: 422,
                description: "Validation error"
            ),
            new OA\Response(
                response: 403,
                description: "Access denied - not enrolled or deadline passed"
            )
        ]
    )]
    public function submitAssignment(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|integer|exists:assignments,id',
            'content' => 'nullable|string|max:10000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png,zip|max:10240', // 10MB max
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $assignmentId = $request->assignment_id;

        // Get assignment with lesson and course details
        $assignment = Assignment::with([
            'lesson.module.course',
            'lesson.module.course.enrollments' => function($q) use ($user) {
                $q->where('user_id', $user->id);
            }
        ])->findOrFail($assignmentId);

        $course = $assignment->lesson->module->course;
        $enrollment = $course->enrollments->first();

        // Check if user is enrolled in this course
        if (!$enrollment) {
            throw ValidationException::withMessages([
                'assignment_id' => ['You are not enrolled in this course.']
            ]);
        }

        // Check if enrollment is active
        if ($enrollment->status !== 'active') {
            throw ValidationException::withMessages([
                'assignment_id' => ['Your enrollment is not active.']
            ]);
        }

        // Check batch timing if applicable
        if ($enrollment->batch) {
            $batch = $enrollment->batch;
            $now = Carbon::now();
            
            // Check if batch has started
            if ($batch->start_date && Carbon::parse($batch->start_date)->isFuture()) {
                throw ValidationException::withMessages([
                    'assignment_id' => ['Course batch has not started yet. Start date: ' . Carbon::parse($batch->start_date)->format('Y-m-d')]
                ]);
            }

            // Check if batch has ended
            if ($batch->end_date && Carbon::parse($batch->end_date)->isPast()) {
                throw ValidationException::withMessages([
                    'assignment_id' => ['Course batch has ended. End date: ' . Carbon::parse($batch->end_date)->format('Y-m-d')]
                ]);
            }
        }

        // Check assignment deadline
        if ($assignment->deadline && Carbon::parse($assignment->deadline)->isPast()) {
            throw ValidationException::withMessages([
                'assignment_id' => ['Assignment deadline has passed. Deadline was: ' . Carbon::parse($assignment->deadline)->format('Y-m-d H:i')]
            ]);
        }

        // Check if assignment allows multiple submissions
        $existingSubmission = Submission::where('user_id', $user->id)
            ->where('assignment_id', $assignmentId)
            ->first();

        if ($existingSubmission && !$assignment->allow_resubmission) {
            throw ValidationException::withMessages([
                'assignment_id' => ['This assignment does not allow resubmission.']
            ]);
        }

        // Create or update submission
        $submission = Submission::firstOrNew([
            'user_id' => $user->id,
            'assignment_id' => $assignmentId,
        ]);

        $submission->content = $request->content;
        $submission->notes = $request->notes;
        $submission->status = 'submitted';
        $submission->submitted_at = Carbon::now();

        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $user->id . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('submissions/' . $assignmentId, $fileName, 'public');
            
            $submission->file_path = $filePath;
            $submission->file_name = $file->getClientOriginalName();
            $submission->file_size = $file->getSize();
        }

        $submission->save();

        return response()->json([
            'message' => $existingSubmission ? 'Assignment updated successfully' : 'Assignment submitted successfully',
            'submission' => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'content' => $submission->content,
                'notes' => $submission->notes,
                'status' => $submission->status,
                'file_url' => $submission->file_path ? Storage::disk('public')->url($submission->file_path) : null,
                'file_name' => $submission->file_name,
                'submitted_at' => $submission->submitted_at,
                'updated_at' => $submission->updated_at,
            ],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'deadline' => $assignment->deadline,
                'max_marks' => $assignment->max_marks ?? null,
                'allow_resubmission' => $assignment->allow_resubmission ?? false,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'batch' => $enrollment->batch ? [
                'id' => $enrollment->batch->id,
                'name' => $enrollment->batch->name,
                'end_date' => $enrollment->batch->end_date,
            ] : null
        ]);
    }

    #[OA\Get(
        path: "/api/my-assignments",
        summary: "Get user's assignments",
        description: "Get all assignments for enrolled courses with submission status",
        tags: ["User Courses"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                description: "Filter by submission status",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["pending", "submitted", "graded", "overdue"])
            ),
            new OA\Parameter(
                name: "course_id",
                description: "Filter by course ID",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success"
            )
        ]
    )]
    public function myAssignments(Request $request)
    {
        $user = $request->user();
        
        // Get all assignments from enrolled courses
        $enrollments = Enrollment::with([
            'course.modules.lessons.assignments'
        ])
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->get();

        $assignments = collect();
        
        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->course->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    if ($lesson->assignments) {
                        foreach ($lesson->assignments as $assignment) {
                            $submission = Submission::where('user_id', $user->id)
                                ->where('assignment_id', $assignment->id)
                                ->first();

                            $status = 'pending';
                            if ($submission) {
                                $status = $submission->status;
                            } elseif ($assignment->deadline && Carbon::parse($assignment->deadline)->isPast()) {
                                $status = 'overdue';
                            }

                            // Apply filters
                            if ($request->filled('status') && $status !== $request->status) {
                                continue;
                            }
                            
                            if ($request->filled('course_id') && $enrollment->course->id != $request->course_id) {
                                continue;
                            }

                            $assignments->push([
                                'id' => $assignment->id,
                                'title' => $assignment->title,
                                'description' => $assignment->description,
                                'deadline' => $assignment->deadline,
                                'max_marks' => $assignment->max_marks,
                                'status' => $status,
                                'days_until_deadline' => $assignment->deadline ? Carbon::parse($assignment->deadline)->diffInDays(Carbon::now(), false) : null,
                                'submission' => $submission ? [
                                    'id' => $submission->id,
                                    'submitted_at' => $submission->submitted_at,
                                    'marks_obtained' => $submission->marks_obtained,
                                    'feedback' => $submission->feedback,
                                ] : null,
                                'lesson' => [
                                    'id' => $lesson->id,
                                    'title' => $lesson->title,
                                ],
                                'course' => [
                                    'id' => $enrollment->course->id,
                                    'title' => $enrollment->course->title,
                                ]
                            ]);
                        }
                    }
                }
            }
        }

        // Sort by deadline
        $assignments = $assignments->sortBy('deadline');

        return response()->json($assignments->values());
    }

    /**
     * Calculate next class time based on batch schedule
     */
    private function calculateNextClassTime($batch)
    {
        if (!$batch->schedule) {
            return null;
        }

        // This is a simplified example - you'd implement based on your schedule format
        // Assuming schedule is stored as JSON with days and times
        try {
            $schedule = json_decode($batch->schedule, true);
            $now = Carbon::now($batch->timezone ?? 'UTC');
            
            // Implementation depends on your schedule format
            // Return next upcoming class time
            
            return null; // Placeholder
        } catch (\Exception $e) {
            return null;
        }
    }
}