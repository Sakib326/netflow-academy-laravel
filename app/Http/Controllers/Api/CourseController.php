<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseReview;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CourseController extends Controller
{
    #[OA\Get(
        path: "/api/courses",
        summary: "Get paginated list of courses",
        description: "Get all active courses with pagination and basic information",
        tags: ["Courses"],
        parameters: [
            new OA\Parameter(
                name: "page",
                description: "Page number",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "per_page",
                description: "Items per page (max 50)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 15)
            ),
            new OA\Parameter(
                name: "category_id",
                description: "Filter by category ID",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "search",
                description: "Search in course title and description",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "sort",
                description: "Sort by field",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["latest", "popular", "price_low", "price_high"], example: "latest")
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
                            items: new OA\Items(ref: "#/components/schemas/CourseList")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "last_page", type: "integer", example: 5),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 67)
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $perPage = min($request->get('per_page', 15), 50);

        $query = Course::with(['category', 'instructor', 'reviews'])
            ->where('is_active', true)
            ->withCount(['enrollments', 'lessons', 'reviews']);

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        switch ($request->get('sort', 'latest')) {
            case 'popular':
                $query->orderBy('enrollments_count', 'desc');
                break;
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            default:
                $query->latest();
        }

        $courses = $query->paginate($perPage);

        // Transform the data
        $courses->getCollection()->transform(function ($course) {
            return [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->short_description,
                'thumbnail' => $course->thumbnail ? asset('storage/' . $course->thumbnail) : null,
                'price' => $course->price,
                'discounted_price' => $course->discounted_price,
                'duration' => $course->duration,
                'level' => $course->level,
                'language' => $course->language,
                'status' => $course->status,
                'total_lessons' => $course->lessons_count,
                'total_students' => $course->enrollments_count,
                'average_rating' => round($course->reviews->avg('rating') ?? 0, 1),
                'total_reviews' => $course->reviews_count,
                'category' => [
                    'id' => $course->category->id,
                    'name' => $course->category->name,
                    'slug' => $course->category->slug,
                ],
                'instructor' => [
                    'id' => $course->instructor->id,
                    'name' => $course->instructor->name,
                    'avatar' => $course->instructor->avatar ? asset('storage/' . $course->instructor->avatar) : null,
                ],
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ];
        });

        return response()->json($courses);
    }

    #[OA\Get(
        path: "/api/courses/{slug}",
        summary: "Get course details by slug",
        description: "Get detailed course information including modules, lessons, instructor, and reviews",
        tags: ["Courses"],
        parameters: [
            new OA\Parameter(
                name: "slug",
                description: "Course slug",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", example: "introduction-to-laravel")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(ref: "#/components/schemas/CourseDetail")
            ),
            new OA\Response(
                response: 404,
                description: "Course not found"
            )
        ]
    )]
    public function show($slug)
    {
        $course = Course::with([
            'category',
            'instructor',
            'modules' => function($query) {
                $query->orderBy('order_index');
            },
            'modules.lessons' => function($query) {
                $query->orderBy('order_index');
            },
            'reviews' => function($query) {
                $query->with('user')->latest()->take(10);
            }
        ])
        ->withCount(['enrollments', 'lessons', 'reviews'])
        ->where('is_active', true)
        ->where('slug', $slug)
        ->firstOrFail();

        // Get only free lessons content
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
                        'description' => $lesson->description,
                        'duration' => $lesson->duration,
                        'lesson_type' => $lesson->lesson_type,
                        'order' => $lesson->order_index,
                        'is_free' => $lesson->is_free,
                        // Only include content for free lessons
                        'content' => $lesson->is_free ? $lesson->content : null,
                        'video_url' => $lesson->is_free ? $lesson->video_url : null,
                    ];
                })
            ];
        });

        // Calculate average rating
        $averageRating = $course->reviews->avg('rating') ?? 0;

        // Get rating distribution
        $ratingDistribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $course->reviews->where('rating', $i)->count();
            $ratingDistribution[$i] = [
                'rating' => $i,
                'count' => $count,
                'percentage' => $course->reviews_count > 0 ? round(($count / $course->reviews_count) * 100, 1) : 0
            ];
        }

        $courseData = [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'short_description' => $course->short_description,
            'thumbnail' => $course->thumbnail ? asset('storage/' . $course->thumbnail) : null,
            'price' => $course->price,
            'discounted_price' => $course->discounted_price,
            'duration' => $course->duration,
            'level' => $course->level,
            'language' => $course->language,
            'status' => $course->status,
            'requirements' => $course->requirements,
            'what_you_will_learn' => $course->what_you_will_learn,
            'total_lessons' => $course->lessons_count,
            'total_students' => $course->enrollments_count,
            'average_rating' => round($averageRating, 1),
            'total_reviews' => $course->reviews_count,
            'rating_distribution' => array_values($ratingDistribution),
            'category' => [
                'id' => $course->category->id,
                'name' => $course->category->name,
                'slug' => $course->category->slug,
            ],
            'instructor' => [
                'id' => $course->instructor->id,
                'name' => $course->instructor->name,
                'email' => $course->instructor->email,
                'avatar' => $course->instructor->avatar ? asset('storage/' . $course->instructor->avatar) : null,
                'bio' => $course->instructor->bio ?? '',
                'designation' => $course->instructor->designation ?? '',
                'total_courses' => $course->instructor->courses()->where('is_active', true)->count(),
                'total_students' => $course->instructor->courses()->withCount('enrollments')->get()->sum('enrollments_count'),
            ],
            'modules' => $modules,
            'recent_reviews' => $course->reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'user' => [
                        'name' => $review->user->name,
                        'avatar' => $review->user->avatar ? asset('storage/' . $review->user->avatar) : null,
                    ]
                ];
            }),
            'created_at' => $course->created_at,
            'updated_at' => $course->updated_at,
        ];

        return response()->json($courseData);
    }

    #[OA\Get(
        path: "/api/categories",
        summary: "Get all categories",
        description: "Get all categories with course count",
        tags: ["Categories"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Category")
                )
            )
        ]
    )]
    public function categories()
    {
        $categories = CourseCategory::withCount(['courses'])
            ->orderBy('name')
            ->get();

        $categoriesData = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'courses_count' => $category->courses_count,
            ];
        });

        return response()->json($categoriesData);
    }

    #[OA\Get(
        path: "/api/instructors",
        summary: "Get all instructors",
        description: "Get all active instructors with their statistics",
        tags: ["Instructors"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Instructor")
                )
            )
        ]
    )]
    public function instructors()
    {
        $instructors = User::whereIn('role', ['instructor', 'admin'])
            ->where('is_active', true)
            ->withCount(['courses' => function($query) {
                $query->where('is_active', true);
            }])
            ->with(['courses' => function($query) {
                $query->where('is_active', true)->withCount('enrollments');
            }])
            ->orderBy('name')
            ->get();

        $instructorsData = $instructors->map(function ($instructor) {
            $totalStudents = $instructor->courses->sum('enrollments_count');

            return [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'email' => $instructor->email,
                'avatar' => $instructor->avatar ? asset('storage/' . $instructor->avatar) : null,
                'bio' => $instructor->bio ?? '',
                'designation' => $instructor->designation ?? '',
                'role' => $instructor->role,
                'total_courses' => $instructor->courses_count,
                'total_students' => $totalStudents,
                'created_at' => $instructor->created_at,
            ];
        });

        return response()->json($instructorsData);
    }

    #[OA\Get(
        path: "/api/reviews",
        summary: "Get paginated reviews",
        description: "Get all course reviews with pagination",
        tags: ["Reviews"],
        parameters: [
            new OA\Parameter(
                name: "page",
                description: "Page number",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "per_page",
                description: "Items per page (max 50)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", example: 15)
            ),
            new OA\Parameter(
                name: "course_id",
                description: "Filter by course ID",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "rating",
                description: "Filter by rating",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", minimum: 1, maximum: 5)
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
                            items: new OA\Items(ref: "#/components/schemas/Review")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "last_page", type: "integer", example: 5),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 67)
                    ]
                )
            )
        ]
    )]
    public function reviews(Request $request)
    {
        $perPage = min($request->get('per_page', 15), 50);

        $query = CourseReview::with(['user', 'course'])
            ->latest();

        // Course filter
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Rating filter
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->paginate($perPage);

        // Transform the data
        $reviews->getCollection()->transform(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'review' => $review->review,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
                'user' => [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'avatar' => $review->user->avatar ? asset('storage/' . $review->user->avatar) : null,
                ],
                'course' => [
                    'id' => $review->course->id,
                    'title' => $review->course->title,
                    'slug' => $review->course->slug,
                    'thumbnail' => $review->course->thumbnail ? asset('storage/' . $review->course->thumbnail) : null,
                ]
            ];
        });

        return response()->json($reviews);
    }
}