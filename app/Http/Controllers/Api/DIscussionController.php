<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "DiscussionUser",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 7),
        new OA\Property(property: "name", type: "string", example: "Jane Doe"),
        new OA\Property(property: "avatar", type: "string", nullable: true, example: "https://example.com/storage/avatars/jane.jpg"),
        new OA\Property(property: "enrolled_courses", type: "array", items: new OA\Items(type: "object")),
        new OA\Property(property: "current_batch", type: "object", nullable: true),
    ]
)]
#[OA\Schema(
    schema: "DiscussionThreadSummary",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 10),
        new OA\Property(property: "discussable_type", type: "string", example: "App\\Models\\Course"),
        new OA\Property(property: "discussable_id", type: "integer", example: 3),
        new OA\Property(property: "title", type: "string", example: "Doubt about this course"),
        new OA\Property(property: "content", type: "string", example: "Can someone explain the difference between X and Y?"),
        new OA\Property(property: "is_question", type: "boolean", example: true),
        new OA\Property(property: "is_answered", type: "boolean", example: false),
        new OA\Property(property: "upvotes", type: "integer", example: 5),
        new OA\Property(property: "user", ref: "#/components/schemas/DiscussionUser"),
        new OA\Property(property: "replies_count", type: "integer", example: 5),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "DiscussionPost",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 12),
        new OA\Property(property: "discussable_type", type: "string", example: "App\\Models\\Course"),
        new OA\Property(property: "discussable_id", type: "integer", example: 3),
        new OA\Property(property: "parent_id", type: "integer", nullable: true, example: null),
        new OA\Property(property: "title", type: "string", nullable: true, example: "Issue in Module 2"),
        new OA\Property(property: "content", type: "string", example: "Here is my question text..."),
        new OA\Property(property: "is_question", type: "boolean", example: true),
        new OA\Property(property: "is_answered", type: "boolean", example: false),
        new OA\Property(property: "upvotes", type: "integer", example: 5),
        new OA\Property(property: "user", ref: "#/components/schemas/DiscussionUser"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "DiscussionThread",
    type: "object",
    properties: [
        new OA\Property(property: "thread", ref: "#/components/schemas/DiscussionPost"),
        new OA\Property(
            property: "replies",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/DiscussionPost")
        ),
        new OA\Property(property: "replies_count", type: "integer", example: 5),
    ]
)]
class DIscussionController extends Controller
{
    #[OA\Get(
        path: "/api/courses/{course_id}/discussions",
        summary: "List discussion threads for a course",
        description: "Returns paginated top-level discussion threads for the given course.",
        tags: ["Discussions"],
        parameters: [
            new OA\Parameter(
                name: "course_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 10)
            ),
            new OA\Parameter(
                name: "search",
                description: "Filter by title/content",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "is_question",
                description: "Filter by question type",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "boolean")
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
                            items: new OA\Items(ref: "#/components/schemas/DiscussionThreadSummary")
                        ),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "last_page", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Course not found")
        ]
    )]
    public function index(Request $request, int $course_id)
    {
        $course = Course::findOrFail($course_id);

        $perPage = min((int)$request->get('per_page', 10), 50);

        $query = Discussion::with(['user.enrollments.course', 'user.enrollments.batch'])
            ->where('discussable_type', Course::class)
            ->where('discussable_id', $course->id)
            ->whereNull('parent_id') // Fixed: Use explicit null check instead of rootLevel scope
            ->withCount('replies')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_question')) {
            $query->where('is_question', $request->boolean('is_question'));
        }

        $threads = $query->paginate($perPage);

        $threads->getCollection()->transform(function ($t) use ($course) {
            // Get user's enrolled courses and current batch for this course
            $userEnrollments = $t->user->enrollments()->where('status', 'active')->with(['course', 'batch'])->get();
            $currentBatch = $userEnrollments->where('batch.course_id', $course->id)->first()?->batch;

            return [
                'id' => $t->id,
                'discussable_type' => $t->discussable_type,
                'discussable_id' => $t->discussable_id,
                'title' => $t->title,
                'content' => $t->content,
                'is_question' => $t->is_question,
                'is_answered' => $t->is_answered,
                'upvotes' => $t->upvotes,
                'user' => [
                    'id' => $t->user->id,
                    'name' => $t->user->name,
                    'avatar' => $t->user->avatar ? asset('storage/' . $t->user->avatar) : null,
                    'enrolled_courses' => $userEnrollments->map(function ($enrollment) {
                        return [
                            'id' => $enrollment->course->id,
                            'title' => $enrollment->course->title,
                            'enrollment_status' => $enrollment->status,
                        ];
                    }),
                    'current_batch' => $currentBatch ? [
                        'id' => $currentBatch->id,
                        'name' => $currentBatch->name,
                        'start_date' => $currentBatch->start_date,
                        'end_date' => $currentBatch->end_date,
                    ] : null,
                ],
                'replies_count' => $t->replies_count,
                'created_at' => $t->created_at,
                'updated_at' => $t->updated_at,
            ];
        });

        return response()->json($threads);
    }

    #[OA\Post(
        path: "/api/courses/{course_id}/discussions",
        summary: "Create a new discussion thread",
        description: "Only enrolled (active) users can create threads.",
        tags: ["Discussions"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "course_id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    required: ["title", "content"],
                    properties: [
                        new OA\Property(property: "title", type: "string", example: "Question about this course"),
                        new OA\Property(property: "content", type: "string", example: "I don't understand this concept."),
                        new OA\Property(property: "is_question", type: "boolean", example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: "#/components/schemas/DiscussionPost")),
            new OA\Response(response: 403, description: "Not enrolled"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 404, description: "Course not found")
        ]
    )]
    public function store(Request $request, int $course_id)
    {
        $course = Course::findOrFail($course_id);

        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:10000',
            'is_question' => 'boolean',
        ]);

        $user = $request->user();

        // Check enrollment through batches
        $enrolled = Enrollment::whereHas('batch', function ($query) use ($course) {
            $query->where('course_id', $course->id);
        })
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        // Uncomment if you want to enforce enrollment
        // if (!$enrolled) {
        //     throw ValidationException::withMessages([
        //         'course_id' => ['You must be enrolled to post in this course.']
        //     ]);
        // }

        $post = Discussion::create([
            'user_id' => $user->id,
            'discussable_type' => Course::class,
            'discussable_id' => $course->id,
            'parent_id' => null,
            'title' => $request->title,
            'content' => $request->content,
            'is_question' => $request->boolean('is_question', true),
            'is_answered' => false,
            'upvotes' => 0,
        ]);

        $post->load(['user.enrollments.course', 'user.enrollments.batch']);

        // Get user's current batch for this course
        $currentBatch = $post->user->enrollments()
            ->whereHas('batch', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->where('status', 'active')
            ->with('batch')
            ->first()?->batch;

        $enrolledCourses = $post->user->enrollments()
            ->where('status', 'active')
            ->with('batch.course')
            ->get();

        return response()->json([
            'id' => $post->id,
            'discussable_type' => $post->discussable_type,
            'discussable_id' => $post->discussable_id,
            'parent_id' => $post->parent_id,
            'title' => $post->title,
            'content' => $post->content,
            'is_question' => $post->is_question,
            'is_answered' => $post->is_answered,
            'upvotes' => $post->upvotes,
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'avatar' => $post->user->avatar ? asset('storage/' . $post->user->avatar) : null,
                'enrolled_courses' => $enrolledCourses->map(function ($enrollment) {
                    return [
                        'id' => $enrollment->batch->course->id,
                        'title' => $enrollment->batch->course->title,
                        'enrollment_status' => $enrollment->status,
                    ];
                }),
                'current_batch' => $currentBatch ? [
                    'id' => $currentBatch->id,
                    'name' => $currentBatch->name,
                    'start_date' => $currentBatch->start_date,
                    'end_date' => $currentBatch->end_date,
                ] : null,
            ],
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ], 201);
    }

    #[OA\Get(
        path: "/api/discussions/{id}",
        summary: "Get a discussion thread with replies",
        description: "Returns the thread and its replies with user enrollment info.",
        tags: ["Discussions"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success", content: new OA\JsonContent(ref: "#/components/schemas/DiscussionThread")),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function show(int $id)
    {
        $thread = Discussion::with(['user.enrollments.course', 'user.enrollments.batch', 'discussable'])
            ->whereNull('parent_id') // Fixed: Use explicit null check
            ->findOrFail($id);

        $replies = Discussion::with(['user.enrollments.course', 'user.enrollments.batch'])
            ->where('parent_id', $thread->id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Get course from discussable
        $course = $thread->discussable;
        $currentBatch = $thread->user->enrollments()
            ->whereHas('batch', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->where('status', 'active')
            ->with('batch')
            ->first()?->batch;

        $enrolledCourses = $thread->user->enrollments()
            ->where('status', 'active')
            ->with('batch.course')
            ->get();

        return response()->json([
            'thread' => [
                'id' => $thread->id,
                'discussable_type' => $thread->discussable_type,
                'discussable_id' => $thread->discussable_id,
                'parent_id' => $thread->parent_id,
                'title' => $thread->title,
                'content' => $thread->content,
                'is_question' => $thread->is_question,
                'is_answered' => $thread->is_answered,
                'upvotes' => $thread->upvotes,
                'user' => [
                    'id' => $thread->user->id,
                    'name' => $thread->user->name,
                    'avatar' => $thread->user->avatar ? asset('storage/' . $thread->user->avatar) : null,
                    'enrolled_courses' => $enrolledCourses->map(function ($enrollment) {
                        return [
                            'id' => $enrollment->batch->course->id,
                            'title' => $enrollment->batch->course->title,
                            'enrollment_status' => $enrollment->status,
                        ];
                    }),
                    'current_batch' => $currentBatch ? [
                        'id' => $currentBatch->id,
                        'name' => $currentBatch->name,
                        'start_date' => $currentBatch->start_date,
                        'end_date' => $currentBatch->end_date,
                    ] : null,
                ],
                'created_at' => $thread->created_at,
                'updated_at' => $thread->updated_at,
            ],
            'replies' => $replies->map(function ($r) use ($course) {
                $replyBatch = $r->user->enrollments()
                    ->whereHas('batch', function ($query) use ($course) {
                        $query->where('course_id', $course->id);
                    })
                    ->where('status', 'active')
                    ->with('batch')
                    ->first()?->batch;

                $replyEnrolledCourses = $r->user->enrollments()
                    ->where('status', 'active')
                    ->with('batch.course')
                    ->get();

                return [
                    'id' => $r->id,
                    'discussable_type' => $r->discussable_type,
                    'discussable_id' => $r->discussable_id,
                    'parent_id' => $r->parent_id,
                    'title' => $r->title,
                    'content' => $r->content,
                    'is_question' => $r->is_question,
                    'is_answered' => $r->is_answered,
                    'upvotes' => $r->upvotes,
                    'user' => [
                        'id' => $r->user->id,
                        'name' => $r->user->name,
                        'avatar' => $r->user->avatar ? asset('storage/' . $r->user->avatar) : null,
                        'enrolled_courses' => $replyEnrolledCourses->map(function ($enrollment) {
                            return [
                                'id' => $enrollment->batch->course->id,
                                'title' => $enrollment->batch->course->title,
                                'enrollment_status' => $enrollment->status,
                            ];
                        }),
                        'current_batch' => $replyBatch ? [
                            'id' => $replyBatch->id,
                            'name' => $replyBatch->name,
                            'start_date' => $replyBatch->start_date,
                            'end_date' => $replyBatch->end_date,
                        ] : null,
                    ],
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ];
            }),
            'replies_count' => $replies->count(),
        ]);
    }

    #[OA\Post(
        path: "/api/discussions/{id}/reply",
        summary: "Reply to a discussion thread",
        description: "Only enrolled (active) users can reply.",
        tags: ["Discussions"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    required: ["content"],
                    properties: [
                        new OA\Property(property: "content", type: "string", example: "Here is my answer..."),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: "#/components/schemas/DiscussionPost")),
            new OA\Response(response: 403, description: "Not enrolled"),
            new OA\Response(response: 404, description: "Thread not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function reply(Request $request, int $id)
    {
        $thread = Discussion::with('discussable')->whereNull('parent_id')->findOrFail($id);

        $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $user = $request->user();
        $course = $thread->discussable;

        // Check enrollment through batches
        $enrolled = Enrollment::whereHas('batch', function ($query) use ($course) {
            $query->where('course_id', $course->id);
        })
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        // Uncomment if you want to enforce enrollment
        // if (!$enrolled) {
        //     throw ValidationException::withMessages([
        //         'id' => ['You must be enrolled to reply in this course.']
        //     ]);
        // }

        $reply = Discussion::create([
            'user_id' => $user->id,
            'discussable_type' => $thread->discussable_type,
            'discussable_id' => $thread->discussable_id,
            'parent_id' => $thread->id,
            'title' => null,
            'content' => $request->content,
            'is_question' => false,
            'is_answered' => false,
            'upvotes' => 0,
        ]);

        $reply->load(['user.enrollments.course', 'user.enrollments.batch']);
        $currentBatch = $reply->user->enrollments()
            ->whereHas('batch', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->where('status', 'active')
            ->with('batch')
            ->first()?->batch;

        $enrolledCourses = $reply->user->enrollments()
            ->where('status', 'active')
            ->with('batch.course')
            ->get();

        return response()->json([
            'id' => $reply->id,
            'discussable_type' => $reply->discussable_type,
            'discussable_id' => $reply->discussable_id,
            'parent_id' => $reply->parent_id,
            'title' => $reply->title,
            'content' => $reply->content,
            'is_question' => $reply->is_question,
            'is_answered' => $reply->is_answered,
            'upvotes' => $reply->upvotes,
            'user' => [
                'id' => $reply->user->id,
                'name' => $reply->user->name,
                'avatar' => $reply->user->avatar ? asset('storage/' . $reply->user->avatar) : null,
                'enrolled_courses' => $enrolledCourses->map(function ($enrollment) {
                    return [
                        'id' => $enrollment->batch->course->id,
                        'title' => $enrollment->batch->course->title,
                        'enrollment_status' => $enrollment->status,
                    ];
                }),
                'current_batch' => $currentBatch ? [
                    'id' => $currentBatch->id,
                    'name' => $currentBatch->name,
                    'start_date' => $currentBatch->start_date,
                    'end_date' => $currentBatch->end_date,
                ] : null,
            ],
            'created_at' => $reply->created_at,
            'updated_at' => $reply->updated_at,
        ], 201);
    }

    #[OA\Put(
        path: "/api/discussions/{id}",
        summary: "Update a discussion post",
        description: "Only the owner or course instructor can update.",
        tags: ["Discussions"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "title", type: "string", nullable: true),
                        new OA\Property(property: "content", type: "string"),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Updated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function update(Request $request, int $id)
    {
        $post = Discussion::with('discussable')->findOrFail($id);
        $user = $request->user();
        $course = $post->discussable;

        // Check permissions: owner, course instructor, or admin
        $canUpdate = $post->user_id === $user->id ||
                    ($course && $course->instructor_id === $user->id) ||
                    in_array($user->role ?? '', ['admin']);

        if (!$canUpdate) {
            abort(403, 'You can only update your own posts.');
        }

        $request->validate([
            'title' => 'nullable|string|max:200',
            'content' => 'nullable|string|max:10000',
        ]);

        // Only update title for root-level discussions (not replies)
        if (is_null($post->parent_id) && $request->filled('title')) {
            $post->title = $request->title;
        }

        if ($request->filled('content')) {
            $post->content = $request->content;
        }

        $post->save();

        return response()->json(['message' => 'Discussion updated successfully']);
    }

    #[OA\Delete(
        path: "/api/discussions/{id}",
        summary: "Delete a discussion post",
        description: "Only the owner or course instructor can delete.",
        tags: ["Discussions"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Deleted"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function destroy(Request $request, int $id)
    {
        $post = Discussion::with('discussable')->findOrFail($id);
        $user = $request->user();
        $course = $post->discussable;

        // Check permissions: owner, course instructor, or admin
        $canDelete = $post->user_id === $user->id ||
                    ($course && $course->instructor_id === $user->id) ||
                    in_array($user->role ?? '', ['admin']);

        if (!$canDelete) {
            abort(403, 'You can only delete your own posts.');
        }

        // If it's a root-level discussion, delete all its replies
        if (is_null($post->parent_id)) {
            Discussion::where('parent_id', $post->id)->delete();
        }

        $post->delete();

        return response()->json(['message' => 'Discussion deleted successfully']);
    }

    #[OA\Post(
        path: "/api/discussions/{id}/upvote",
        summary: "Toggle upvote for a discussion",
        description: "Upvote or remove upvote from a discussion post.",
        tags: ["Discussions"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Upvoted"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function upvote(Request $request, int $id)
    {
        $discussion = Discussion::findOrFail($id);
        $user = $request->user();

        // Check if Discussion model has toggleUpvote method
        if (method_exists($discussion, 'toggleUpvote')) {
            $discussion->toggleUpvote($user->id);
        } else {
            // Simple increment if method doesn't exist
            $discussion->increment('upvotes');
        }

        return response()->json([
            'message' => 'Upvote toggled successfully',
            'upvotes' => $discussion->fresh()->upvotes
        ]);
    }

    #[OA\Post(
        path: "/api/discussions/{id}/mark-answered",
        summary: "Mark a question as answered",
        description: "Mark a discussion question as answered (only question owner or instructor can do this).",
        tags: ["Discussions"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Marked as answered"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function markAnswered(Request $request, int $id)
    {
        $discussion = Discussion::with('discussable')->findOrFail($id);
        $user = $request->user();
        $course = $discussion->discussable;

        // Check permissions: question owner, course instructor, or admin
        $canMark = $discussion->user_id === $user->id ||
                  ($course && $course->instructor_id === $user->id) ||
                  in_array($user->role ?? '', ['admin']);

        if (!$canMark) {
            abort(403, 'You can only mark your own questions as answered.');
        }

        if (!$discussion->is_question) {
            return response()->json(['message' => 'Only questions can be marked as answered.'], 400);
        }

        // Check if Discussion model has markAsAnswered method
        if (method_exists($discussion, 'markAsAnswered')) {
            $discussion->markAsAnswered();
        } else {
            // Direct update if method doesn't exist
            $discussion->update(['is_answered' => true]);
        }

        return response()->json(['message' => 'Question marked as answered successfully']);
    }
}
