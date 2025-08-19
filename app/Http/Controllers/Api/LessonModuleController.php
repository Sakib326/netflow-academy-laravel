<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class LessonModuleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/modules",
     *     summary="Get all modules for a course (with lessons, filtered by enrollment)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="course_id",
     *         in="query",
     *         required=true,
     *         description="Course ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Modules with lessons",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="modules", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="order_index", type="integer"),
     *                     @OA\Property(property="lessons", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="title", type="string"),
     *                             @OA\Property(property="slug", type="string"),
     *                             @OA\Property(property="is_free", type="boolean"),
     *                             @OA\Property(property="type", type="string"),
     *                             @OA\Property(property="order_index", type="integer"),
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="enrolled", type="boolean")
     *         )
     *     )
     * )
     */
    public function modules(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $courseId = $request->input('course_id');
        $user = Auth::user();

        // Check if user is enrolled in this course
        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->exists();

        $modules = Module::where('course_id', $courseId)
            ->with(['lessons' => function ($q) use ($isEnrolled) {
                $q->select('id', 'module_id', 'title', 'slug', 'is_free', 'type', 'order_index')
                  ->orderBy('order_index');
                if (!$isEnrolled) {
                    $q->where('is_free', true);
                }
            }])
            ->orderBy('order_index')
            ->get(['id', 'course_id', 'title', 'order_index']);

        return response()->json([
            'success' => true,
            'modules' => $modules,
            'enrolled' => $isEnrolled,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/lessons/{lesson}",
     *     summary="Get lesson details (only if free or user is enrolled)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lesson",
     *         in="path",
     *         required=true,
     *         description="Lesson ID",
     *         @OA\Schema(type="integer")
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
     *     @OA\Response(
     *         response=403,
     *         description="You must purchase this course to access this lesson."
     *     )
     * )
     */
    public function lesson(Request $request, $lessonId)
    {
        $lesson = Lesson::with(['module:id,course_id,title', 'module.course:id,title'])
            ->select('id', 'module_id', 'title', 'slug', 'type', 'content', 'is_free', 'order_index', 'status')
            ->findOrFail($lessonId);

        $user = Auth::user();
        $courseId = $lesson->module->course_id ?? null;

        $isEnrolled = $courseId
            ? Enrollment::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->where('status', 'active')
                ->exists()
            : false;

        if (!$lesson->is_free && !$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You must purchase this course to access this lesson.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'lesson' => $lesson,
            'enrolled' => $isEnrolled,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/lessons/{lesson}/submit",
     *     summary="Submit a lesson (quiz or assignment)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lesson",
     *         in="path",
     *         required=true,
     *         description="Lesson ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="content", type="string", description="Assignment text"),
     *                 @OA\Property(property="answers", type="string", description="Quiz answers as JSON array"),
     *                 @OA\Property(property="files[]", type="array", @OA\Items(type="string", format="binary"), description="Files (image, pdf, doc, zip)"),
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
     *     )
     * )
     */
    public function submit(Request $request, $lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);

        $request->validate([
            'content' => 'nullable|string',
            'answers' => 'nullable|string', // JSON string for quiz
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,zip|max:51200',
        ]);

        $user = Auth::user();

        // Check access: only enrolled or free lesson
        $courseId = $lesson->module->course_id ?? null;
        $isEnrolled = $courseId
            ? Enrollment::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->where('status', 'active')
                ->exists()
            : false;
        if (!$lesson->is_free && !$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You must purchase this course to submit this lesson.',
            ], 403);
        }

        // Save uploaded files
        $filePaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filePaths[] = $file->store('submissions/assignments', 'public');
            }
        }

        // Prepare content for assignment or quiz
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
     *     path="/api/lessons/{lesson}/submissions",
     *     summary="Get submissions for a lesson (for the authenticated user)",
     *     tags={"Lessons"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lesson",
     *         in="path",
     *         required=true,
     *         description="Lesson ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Submissions list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="submissions", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function submissions(Request $request, $lessonId)
    {
        $user = Auth::user();

        $submissions = Submission::where('lesson_id', $lessonId)
            ->where('user_id', $user->id)
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json([
            'success' => true,
            'submissions' => $submissions,
        ]);
    }
}
