<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class LessonModuleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/modules/{slug}",
     *     summary="Get all modules for a course by course slug (with lessons)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Course slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Modules with lessons",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="modules", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="enrolled", type="boolean")
     *         )
     *     )
     * )
     */
    public function modulesBySlug(string $slug)
    {
        $user = Auth::user();

        $course = Course::with(['modules.lessons'])
            ->where('slug', $slug)
            ->firstOrFail();

        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('batch', function ($q) use ($course) {
                $q->where('course_id', $course->id);
            })
            ->exists();

        $modules = $course->modules->map(function ($module) {
            return [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'order' => $module->order_index,
                'lessons' => $module->lessons->map(function ($lesson) {
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'slug' => $lesson->slug,
                        'description' => $lesson->description,
                        'duration' => $lesson->duration,
                        'lesson_type' => $lesson->type,
                        'order' => $lesson->order_index,
                        'is_free' => $lesson->is_free,
                        'content' => $lesson->content,
                        'questions' => $lesson->questions,
                        'files' => $lesson->files,
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'modules' => $modules,
            'enrolled' => $isEnrolled,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/lessons/{slug}",
     *     summary="Get lesson details by lesson slug (authorized only)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Lesson slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lesson details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="lesson", type="object"),
     *             @OA\Property(property="enrolled", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=403, description="You must purchase this course to access this lesson."),
     *     @OA\Response(response=404, description="Lesson not found")
     * )
     */
    public function lessonBySlug(string $slug)
    {
        $lesson = Lesson::with(['module:id,course_id,title', 'module.course:id,title'])
            ->where('slug', $slug)
            ->firstOrFail();

        $user = Auth::user();
        $course = $lesson->module->course ?? null;
        $courseId = $lesson->module->course_id ?? null;

        $isEnrolled = false;
        if ($course && $courseId) {
            $isEnrolled = Enrollment::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('batch', function ($q) use ($course) {
                    $q->where('course_id', $course->id);
                })
                ->exists();
        }

        if (!$lesson->is_free && !$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You must purchase this course to access this lesson.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'description' => $lesson->description,
                'duration' => $lesson->duration,
                'lesson_type' => $lesson->type,
                'order' => $lesson->order_index,
                'is_free' => $lesson->is_free,
                'content' => $lesson->content,
                'questions' => $lesson->questions,
                'files' => $lesson->files,
            ],
            'enrolled' => $isEnrolled,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/lessons/{slug}/submit",
     *     summary="Submit a lesson (assignment or quiz) by lesson slug",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Lesson slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="content", type="string", description="Assignment text"),
     *                 @OA\Property(property="answers", type="string", description="Quiz answers as JSON array"),
     *                 @OA\Property(property="files[]", type="array", @OA\Items(type="string", format="binary"), description="Files (image, pdf, doc, zip)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submission created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="submission", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="You must purchase this course to submit this lesson."),
     *     @OA\Response(response=404, description="Lesson not found")
     * )
     */
    public function submitBySlug(Request $request, string $slug)
    {
        $lesson = Lesson::where('slug', $slug)->firstOrFail();

        $request->validate([
            'content' => 'nullable|string',
            'answers' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:51200',
        ]);

        $user = Auth::user();

        $courseId = $lesson->module->course_id ?? null;
        $isEnrolled = $courseId
            ? Enrollment::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('batch', function ($q) use ($course) {
                    $q->where('course_id', $course->id);
                })
                ->exists()
            : false;

        if (!$lesson->is_free && !$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You must purchase this course to submit this lesson.',
            ], 403);
        }

        $filePaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filePaths[] = $file->store('submissions/assignments', 'public');
            }
        }

        $content = null;
        if ($lesson->type === 'assignment') {
            $content = $request->input('content', '');
        } elseif ($lesson->type === 'quiz') {
            $answers = $request->input('answers', '[]');
            $content = [
                'answers' => json_decode($answers, true) ?? [],
            ];
        }

        $submission = Submission::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'type' => $lesson->type,
            'content' => $content,
            'files' => $filePaths,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/lessons/{slug}/submissions",
     *     summary="Get submissions by lesson slug (for authenticated user)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Lesson slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submissions list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="submissions", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lesson not found")
     * )
     */
    public function submissionsBySlug(string $slug)
    {
        $lesson = Lesson::where('slug', $slug)->firstOrFail();

        $user = Auth::user();

        $submissions = $lesson->submissions()
            ->where('user_id', $user->id)
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json([
            'success' => true,
            'submissions' => $submissions,
        ]);
    }
}
