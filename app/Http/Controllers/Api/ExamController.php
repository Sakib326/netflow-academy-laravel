<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamResponse;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Carbon\Carbon;

#[OA\Schema(
    schema: "ExamQuestion",
    type: "object",
    properties: [
        new OA\Property(property: "question", type: "string", example: "What does PHP stand for?"),
        new OA\Property(property: "options", type: "array", items: new OA\Items(type: "string"), example: ["Personal Home Page", "Private Hypertext Processor", "PHP: Hypertext Preprocessor", "Public Hosting Project"]),
        new OA\Property(property: "answer", type: "integer", example: 2),
    ]
)]
#[OA\Schema(
    schema: "ExamDetail",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "batch_id", type: "integer", example: 1),
        new OA\Property(property: "course_id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "PHP Basics MCQ Exam"),
        new OA\Property(property: "description", type: "string", example: "An exam to test fundamental PHP knowledge."),
        new OA\Property(property: "content", type: "array", items: new OA\Items(ref: "#/components/schemas/ExamQuestion")),
        new OA\Property(property: "total_time", type: "integer", example: 30),
        new OA\Property(property: "status", type: "string", example: "active"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "ExamSummary",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "PHP Basics MCQ Exam"),
        new OA\Property(property: "description", type: "string", example: "An exam to test fundamental PHP knowledge."),
        new OA\Property(property: "total_time", type: "integer", example: 30),
        new OA\Property(property: "status", type: "string", example: "active"),
        new OA\Property(property: "total_questions", type: "integer", example: 3),
        new OA\Property(property: "has_attempted", type: "boolean", example: false),
        new OA\Property(property: "is_attended", type: "boolean", example: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "ExamResponse",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "exam_id", type: "integer", example: 1),
        new OA\Property(property: "batch_id", type: "integer", example: 1),
        new OA\Property(property: "user_id", type: "integer", example: 3),
        new OA\Property(property: "content", type: "array", items: new OA\Items(type: "object")),
        new OA\Property(property: "total_time_taken", type: "integer", example: 25),
        new OA\Property(property: "score", type: "number", format: "float", example: 3.00),
        new OA\Property(property: "max_score", type: "number", format: "float", example: 3.00),
        new OA\Property(property: "percentage", type: "number", format: "float", example: 100.00),
        new OA\Property(property: "status", type: "string", example: "graded"),
        new OA\Property(property: "started_at", type: "string", format: "date-time"),
        new OA\Property(property: "submitted_at", type: "string", format: "date-time"),
        new OA\Property(property: "graded_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "ExamResultDetail",
    type: "object",
    properties: [
        new OA\Property(property: "exam_id", type: "integer", example: 1),
        new OA\Property(property: "exam_title", type: "string", example: "PHP Basics MCQ Exam"),
        new OA\Property(property: "score", type: "number", format: "float", example: 3.00),
        new OA\Property(property: "max_score", type: "number", format: "float", example: 3.00),
        new OA\Property(property: "percentage", type: "number", format: "float", example: 100.00),
        new OA\Property(property: "total_time_taken", type: "integer", example: 25),
        new OA\Property(property: "total_time_allowed", type: "integer", example: 30),
        new OA\Property(property: "status", type: "string", example: "graded"),
        new OA\Property(property: "started_at", type: "string", format: "date-time"),
        new OA\Property(property: "submitted_at", type: "string", format: "date-time"),
        new OA\Property(
            property: "questions",
            type: "array",
            items: new OA\Items(
                type: "object",
                properties: [
                    new OA\Property(property: "question_id", type: "integer", example: 0),
                    new OA\Property(property: "question", type: "string", example: "What does PHP stand for?"),
                    new OA\Property(property: "options", type: "array", items: new OA\Items(type: "string")),
                    new OA\Property(property: "correct_answer", type: "integer", example: 2),
                    new OA\Property(property: "user_answer", type: "integer", example: 2),
                    new OA\Property(property: "is_correct", type: "boolean", example: true),
                ]
            )
        ),
    ]
)]
class ExamController extends Controller
{
    /**
     * Check and auto-fail incomplete exams
     */
    private function checkAndAutoFailExam($user)
    {
        $incompleteExams = ExamResponse::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->with('exam')
            ->get();

        foreach ($incompleteExams as $response) {
            $exam = $response->exam;
            $timeElapsed = $response->started_at->diffInMinutes(now());
            
            // Auto-fail if time exceeded
            if ($timeElapsed >= $exam->total_time) {
                $response->update([
                    'status' => 'auto_failed',
                    'submitted_at' => now(),
                    'graded_at' => now(),
                    'total_time_taken' => $exam->total_time,
                ]);
            }
        }
    }

    #[OA\Get(
        path: "/api/courses/{course_id}/batches/{batch_id}/exams",
        summary: "Get exams for a specific course and batch",
        description: "Get all active exams for a specific course and batch (only enrolled students can access)",
        tags: ["Exams"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "course_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "batch_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "course_id", type: "integer", example: 1),
                        new OA\Property(property: "batch_id", type: "integer", example: 1),
                        new OA\Property(property: "batch_name", type: "string", example: "Batch A"),
                        new OA\Property(
                            property: "exams",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ExamSummary")
                        )
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Not enrolled in this batch"),
            new OA\Response(response: 404, description: "Course or batch not found")
        ]
    )]
    public function getCourseExams(Request $request, int $course_id, int $batch_id)
    {
        $course = Course::findOrFail($course_id);
        $user = $request->user();

        // Check and auto-fail incomplete exams
        $this->checkAndAutoFailExam($user);

        // Check if user is enrolled in the specific batch for this course
        $enrollment = Enrollment::where('batch_id', $batch_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('batch', function($query) use ($course_id) {
                $query->where('course_id', $course_id);
            })
            ->with('batch')
            ->first();

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'batch_id' => ['You must be enrolled in this specific batch to access its exams.']
            ]);
        }

        $batch = $enrollment->batch;

        // Verify batch belongs to the course
        if ($batch->course_id !== $course_id) {
            throw ValidationException::withMessages([
                'batch_id' => ['This batch does not belong to the specified course.']
            ]);
        }

        // Get exams for the specific course and batch
        $exams = Exam::where('course_id', $course_id)
            ->where('batch_id', $batch_id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        $examsData = $exams->map(function ($exam) use ($user) {
            $examResponse = ExamResponse::where('exam_id', $exam->id)
                ->where('user_id', $user->id)
                ->first();

            $hasAttempted = $examResponse && in_array($examResponse->status, ['submitted', 'graded', 'auto_failed']);
            $isAttended = $examResponse !== null;

            return [
                'id' => $exam->id,
                'batch_id' => $exam->batch_id,
                'course_id' => $exam->course_id,
                'title' => $exam->title,
                'description' => $exam->description,
                'total_time' => $exam->total_time,
                'status' => $exam->status,
                'total_questions' => count($exam->content ?? []),
                'has_attempted' => $hasAttempted,
                'is_attended' => $isAttended,
                'created_at' => $exam->created_at,
            ];
        });

        return response()->json([
            'course_id' => $course->id,
            'course_title' => $course->title,
            'batch_id' => $batch->id,
            'batch_name' => $batch->name,
            'exams' => $examsData
        ]);
    }

    #[OA\Post(
        path: "/api/exams/{exam_id}/start",
        summary: "Start an exam",
        description: "Start an exam session (only enrolled students can start)",
        tags: ["Exams"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "exam_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Exam started",
                content: new OA\JsonContent(ref: "#/components/schemas/ExamDetail")
            ),
            new OA\Response(response: 403, description: "Not enrolled or already attempted"),
            new OA\Response(response: 404, description: "Exam not found")
        ]
    )]
    public function startExam(Request $request, int $exam_id)
    {
        $exam = Exam::with(['course', 'batch'])->findOrFail($exam_id);
        $user = $request->user();

        // Check and auto-fail incomplete exams
        $this->checkAndAutoFailExam($user);

        // Check enrollment through batches - must be enrolled in the exact batch
        $enrollment = Enrollment::where('batch_id', $exam->batch_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'exam_id' => ['You must be enrolled in this batch to take this exam.']
            ]);
        }

        // Check if already attempted
        $existingResponse = ExamResponse::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingResponse) {
            throw ValidationException::withMessages([
                'exam_id' => ['You have already attempted this exam.']
            ]);
        }

        // Create exam response entry
        $examResponse = ExamResponse::create([
            'exam_id' => $exam->id,
            'batch_id' => $exam->batch_id,
            'user_id' => $user->id,
            'content' => [],
            'total_time_taken' => 0,
            'score' => 0,
            'max_score' => count($exam->content ?? []),
            'percentage' => 0,
            'status' => 'in_progress',
            'started_at' => now(),
            'submitted_at' => null,
            'graded_at' => null,
        ]);

        // Return exam content without answers
        $examContent = collect($exam->content)->map(function ($question, $index) {
            return [
                'question_id' => $index,
                'question' => $question['question'],
                'options' => $question['options'],
                // Don't send the correct answer to frontend
            ];
        });

        return response()->json([
            'id' => $exam->id,
            'batch_id' => $exam->batch_id,
            'course_id' => $exam->course_id,
            'title' => $exam->title,
            'description' => $exam->description,
            'content' => $examContent,
            'total_time' => $exam->total_time,
            'status' => $exam->status,
            'response_id' => $examResponse->id,
            'started_at' => $examResponse->started_at,
            'batch_name' => $exam->batch->name,
            'course_title' => $exam->course->title,
            'created_at' => $exam->created_at,
            'updated_at' => $exam->updated_at,
        ]);
    }

    #[OA\Post(
        path: "/api/exams/{exam_id}/finish",
        summary: "Finish an exam",
        description: "Submit exam answers and automatically calculate score",
        tags: ["Exams"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "exam_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    required: ["answers"],
                    properties: [
                        new OA\Property(
                            property: "answers",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "question_id", type: "integer", example: 0),
                                    new OA\Property(property: "selected", type: "integer", example: 2),
                                ]
                            )
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Exam submitted and graded",
                content: new OA\JsonContent(ref: "#/components/schemas/ExamResponse")
            ),
            new OA\Response(response: 403, description: "Not enrolled or exam not in progress"),
            new OA\Response(response: 404, description: "Exam not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function finishExam(Request $request, int $exam_id)
    {
        $exam = Exam::with(['course', 'batch'])->findOrFail($exam_id);
        $user = $request->user();

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.selected' => 'required|integer',
        ]);

        // Check enrollment in the specific batch
        $enrollment = Enrollment::where('batch_id', $exam->batch_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'exam_id' => ['You must be enrolled in this batch to take this exam.']
            ]);
        }

        // Find existing response with both exam_id and batch_id
        $examResponse = ExamResponse::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('batch_id', $exam->batch_id)
            ->where('status', 'in_progress')
            ->first();

        if (!$examResponse) {
            throw ValidationException::withMessages([
                'exam_id' => ['No active exam session found. Please start the exam first.']
            ]);
        }

        // Calculate actual time taken from start to now
        $actualTimeTaken = $examResponse->started_at->diffInMinutes(now());
        
        // Don't allow submission if time exceeded
        if ($actualTimeTaken > $exam->total_time) {
            $examResponse->update([
                'status' => 'auto_failed',
                'submitted_at' => now(),
                'graded_at' => now(),
                'total_time_taken' => $exam->total_time,
            ]);
            
            throw ValidationException::withMessages([
                'exam_id' => ['Time limit exceeded. Exam has been automatically failed.']
            ]);
        }

        // Calculate score
        $answers = $request->answers;
        $examQuestions = $exam->content ?? [];
        $score = 0;
        $maxScore = count($examQuestions);

        // Check each answer
        foreach ($answers as $answer) {
            $questionId = $answer['question_id'];
            $selectedOption = $answer['selected'];
            
            if (isset($examQuestions[$questionId]) && 
                $examQuestions[$questionId]['answer'] == $selectedOption) {
                $score++;
            }
        }

        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0;

        // Update exam response
        $examResponse->update([
            'content' => $answers,
            'total_time_taken' => $actualTimeTaken,
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'status' => 'graded',
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        return response()->json([
            'id' => $examResponse->id,
            'exam_id' => $examResponse->exam_id,
            'batch_id' => $examResponse->batch_id,
            'user_id' => $examResponse->user_id,
            'content' => $examResponse->content,
            'total_time_taken' => $examResponse->total_time_taken,
            'score' => $examResponse->score,
            'max_score' => $examResponse->max_score,
            'percentage' => $examResponse->percentage,
            'status' => $examResponse->status,
            'started_at' => $examResponse->started_at,
            'submitted_at' => $examResponse->submitted_at,
            'graded_at' => $examResponse->graded_at,
            'batch_name' => $exam->batch->name,
            'course_title' => $exam->course->title,
            'message' => 'Exam submitted successfully. Score: ' . $score . '/' . $maxScore . ' (' . $percentage . '%)'
        ]);
    }

    #[OA\Get(
        path: "/api/exams/{exam_id}/result",
        summary: "Get exam result summary",
        description: "Get user's exam result summary if already submitted",
        tags: ["Exams"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "exam_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Exam result",
                content: new OA\JsonContent(ref: "#/components/schemas/ExamResponse")
            ),
            new OA\Response(response: 404, description: "Exam or result not found")
        ]
    )]
    public function getExamResult(Request $request, int $exam_id)
    {
        $exam = Exam::findOrFail($exam_id);
        $user = $request->user();

        // Check and auto-fail incomplete exams
        $this->checkAndAutoFailExam($user);

        $examResponse = ExamResponse::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'graded', 'auto_failed'])
            ->first();

        if (!$examResponse) {
            return response()->json(['message' => 'No exam result found.'], 404);
        }

        return response()->json([
            'id' => $examResponse->id,
            'exam_id' => $examResponse->exam_id,
            'batch_id' => $examResponse->batch_id,
            'user_id' => $examResponse->user_id,
            'content' => $examResponse->content,
            'total_time_taken' => $examResponse->total_time_taken,
            'score' => $examResponse->score,
            'max_score' => $examResponse->max_score,
            'percentage' => $examResponse->percentage,
            'status' => $examResponse->status,
            'started_at' => $examResponse->started_at,
            'submitted_at' => $examResponse->submitted_at,
            'graded_at' => $examResponse->graded_at,
        ]);
    }

    #[OA\Get(
        path: "/api/exams/{exam_id}/result/details",
        summary: "Get detailed exam result with answers",
        description: "Get detailed exam result showing correct answers, user answers, and question-wise analysis",
        tags: ["Exams"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "exam_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detailed exam result",
                content: new OA\JsonContent(ref: "#/components/schemas/ExamResultDetail")
            ),
            new OA\Response(response: 404, description: "Exam or result not found")
        ]
    )]
    public function getExamResultDetails(Request $request, int $exam_id)
    {
        $exam = Exam::findOrFail($exam_id);
        $user = $request->user();

        // Check and auto-fail incomplete exams
        $this->checkAndAutoFailExam($user);

        $examResponse = ExamResponse::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'graded', 'auto_failed'])
            ->first();

        if (!$examResponse) {
            return response()->json(['message' => 'No exam result found.'], 404);
        }

        $examQuestions = $exam->content ?? [];
        $userAnswers = $examResponse->content ?? [];

        // Create a map of user answers for easy lookup
        $userAnswerMap = [];
        foreach ($userAnswers as $answer) {
            $userAnswerMap[$answer['question_id']] = $answer['selected'];
        }

        // Build detailed question analysis
        $questions = [];
        foreach ($examQuestions as $index => $question) {
            $userAnswer = $userAnswerMap[$index] ?? null;
            $correctAnswer = $question['answer'];
            $isCorrect = $userAnswer === $correctAnswer;

            $questions[] = [
                'question_id' => $index,
                'question' => $question['question'],
                'options' => $question['options'],
                'correct_answer' => $correctAnswer,
                'correct_answer_text' => $question['options'][$correctAnswer] ?? null,
                'user_answer' => $userAnswer,
                'user_answer_text' => $userAnswer !== null ? ($question['options'][$userAnswer] ?? 'Invalid') : 'Not answered',
                'is_correct' => $isCorrect,
                'points' => $isCorrect ? 1 : 0,
            ];
        }

        // Calculate statistics
        $totalQuestions = count($questions);
        $correctAnswers = collect($questions)->where('is_correct', true)->count();
        $wrongAnswers = collect($questions)->where('is_correct', false)->where('user_answer', '!==', null)->count();
        $notAnswered = collect($questions)->where('user_answer', null)->count();

        return response()->json([
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'exam_description' => $exam->description,
            'score' => $examResponse->score,
            'max_score' => $examResponse->max_score,
            'percentage' => $examResponse->percentage,
            'total_time_taken' => $examResponse->total_time_taken,
            'total_time_allowed' => $exam->total_time,
            'status' => $examResponse->status,
            'started_at' => $examResponse->started_at,
            'submitted_at' => $examResponse->submitted_at,
            'graded_at' => $examResponse->graded_at,
            'statistics' => [
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'not_answered' => $notAnswered,
                'accuracy' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0,
            ],
            'questions' => $questions,
            'batch_info' => [
                'id' => $exam->batch_id,
                'name' => $exam->batch->name ?? 'Unknown',
            ],
            'course_info' => [
                'id' => $exam->course_id,
                'title' => $exam->course->title ?? 'Unknown',
            ],
        ]);
    }
}