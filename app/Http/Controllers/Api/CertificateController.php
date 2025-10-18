<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "CertificateUser",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "name", type: "string", example: "John Doe"),
        new OA\Property(property: "email", type: "string", example: "john@example.com"),
        new OA\Property(property: "avatar", type: "string", nullable: true, example: "https://example.com/storage/avatars/john.jpg"),
    ]
)]
#[OA\Schema(
    schema: "CertificateCourse",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "title", type: "string", example: "Web Development Fundamentals"),
        new OA\Property(property: "slug", type: "string", example: "web-development-fundamentals"),
        new OA\Property(property: "thumbnail", type: "string", nullable: true, example: "https://example.com/storage/courses/course-1.jpg"),
        new OA\Property(property: "description", type: "string", nullable: true, example: "Learn the basics of web development"),
    ]
)]
#[OA\Schema(
    schema: "CertificateExamResponse",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "score", type: "integer", example: 85),
        new OA\Property(property: "total_questions", type: "integer", example: 20),
        new OA\Property(property: "correct_answers", type: "integer", example: 17),
        new OA\Property(property: "percentage", type: "number", format: "float", example: 85.0),
        new OA\Property(property: "completed_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "Certificate",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "certificate_code", type: "string", example: "CERT-WDF-2024-001"),
        new OA\Property(property: "path", type: "string", nullable: true, example: "certificates/cert-123.pdf"),
        new OA\Property(property: "download_url", type: "string", nullable: true, example: "https://example.com/storage/certificates/cert-123.pdf"),
        new OA\Property(property: "issue_date", type: "string", format: "date", example: "2024-01-15"),
        new OA\Property(property: "user", ref: "#/components/schemas/CertificateUser"),
        new OA\Property(property: "course", ref: "#/components/schemas/CertificateCourse"),
        new OA\Property(property: "exam_response", ref: "#/components/schemas/CertificateExamResponse", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class CertificateController extends Controller
{
    #[OA\Get(
        path: "/api/certificates/my-certificates",
        summary: "Get user's own certificates with pagination",
        description: "Returns paginated list of certificates earned by the authenticated user with course and exam details.",
        tags: ["Certificates"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "page",
                in: "query",
                required: false,
                description: "Page number for pagination",
                schema: new OA\Schema(type: "integer", minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                required: false,
                description: "Number of certificates per page (max 50)",
                schema: new OA\Schema(type: "integer", minimum: 1, maximum: 50, example: 10)
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                required: false,
                description: "Search by course title or certificate code",
                schema: new OA\Schema(type: "string", example: "web development")
            ),
            new OA\Parameter(
                name: "course_id",
                in: "query",
                required: false,
                description: "Filter by specific course ID",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "sort_by",
                in: "query",
                required: false,
                description: "Sort certificates by field",
                schema: new OA\Schema(
                    type: "string",
                    enum: ["issue_date", "created_at", "course_title", "certificate_code"],
                    example: "issue_date"
                )
            ),
            new OA\Parameter(
                name: "sort_order",
                in: "query",
                required: false,
                description: "Sort order",
                schema: new OA\Schema(
                    type: "string",
                    enum: ["asc", "desc"],
                    example: "desc"
                )
            ),
            new OA\Parameter(
                name: "year",
                in: "query",
                required: false,
                description: "Filter by issue year",
                schema: new OA\Schema(type: "integer", example: 2024)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificates retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Certificate")
                        ),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "last_page", type: "integer", example: 3),
                        new OA\Property(property: "per_page", type: "integer", example: 10),
                        new OA\Property(property: "total", type: "integer", example: 25),
                        new OA\Property(property: "from", type: "integer", example: 1),
                        new OA\Property(property: "to", type: "integer", example: 10),
                        new OA\Property(property: "path", type: "string", example: "/api/certificates/my-certificates"),
                        new OA\Property(property: "first_page_url", type: "string"),
                        new OA\Property(property: "last_page_url", type: "string"),
                        new OA\Property(property: "next_page_url", type: "string", nullable: true),
                        new OA\Property(property: "prev_page_url", type: "string", nullable: true),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total_certificates", type: "integer", example: 25),
                                new OA\Property(property: "certificates_this_year", type: "integer", example: 12),
                                new OA\Property(property: "latest_certificate_date", type: "string", format: "date", nullable: true),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized - Authentication required"
            ),
            new OA\Response(
                response: 422,
                description: "Validation error"
            ),
            new OA\Response(
                response: 500,
                description: "Internal server error"
            )
        ]
    )]
    public function getMyCertificates(Request $request)
    {
        try {
            // Validate request parameters
            $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:50',
                'search' => 'string|max:255',
                'course_id' => 'integer|exists:courses,id',
                'sort_by' => 'string|in:issue_date,created_at,course_title,certificate_code',
                'sort_order' => 'string|in:asc,desc',
                'year' => 'integer|min:2020|max:' . (date('Y') + 1),
            ]);

            $user = $request->user();
            $perPage = min((int)$request->get('per_page', 10), 50);
            $sortBy = $request->get('sort_by', 'issue_date');
            $sortOrder = $request->get('sort_order', 'desc');

            // Build the query - Fixed relationship name
            $query = Certificate::with([
                'user:id,name,email,avatar',
                'course:id,title,slug,thumbnail,description',
                'examResponse:id,score,total_questions,correct_answers,percentage,completed_at' // Fixed: use examResponse (camelCase)
            ])
            ->where('user_id', $user->id);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('certificate_code', 'like', "%{$search}%")
                      ->orWhereHas('course', function ($courseQuery) use ($search) {
                          $courseQuery->where('title', 'like', "%{$search}%")
                                     ->orWhere('description', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('course_id')) {
                $query->where('course_id', $request->get('course_id'));
            }

            if ($request->filled('year')) {
                $year = $request->get('year');
                $query->whereYear('issue_date', $year);
            }

            // Apply sorting
            switch ($sortBy) {
                case 'course_title':
                    $query->join('courses', 'certificates.course_id', '=', 'courses.id')
                          ->orderBy('courses.title', $sortOrder)
                          ->select('certificates.*');
                    break;
                case 'certificate_code':
                    $query->orderBy('certificate_code', $sortOrder);
                    break;
                case 'created_at':
                    $query->orderBy('created_at', $sortOrder);
                    break;
                case 'issue_date':
                default:
                    $query->orderBy('issue_date', $sortOrder);
                    break;
            }

            // Add secondary sort for consistency
            $query->orderBy('id', 'desc');

            // Get paginated results
            $certificates = $query->paginate($perPage);

            // Transform the data - Fixed relationship access
            $transformedData = $certificates->getCollection()->map(function ($certificate) {
                try {
                    return [
                        'id' => $certificate->id,
                        'certificate_code' => $certificate->certificate_code,
                        'path' => $certificate->path,
                        'download_url' => $certificate->path ? asset('storage/' . $certificate->path) : null,
                        'issue_date' => $certificate->issue_date?->format('Y-m-d'),
                        'user' => $certificate->user ? [
                            'id' => $certificate->user->id,
                            'name' => $certificate->user->name,
                            'email' => $certificate->user->email,
                            'avatar' => $certificate->user->avatar ? asset('storage/' . $certificate->user->avatar) : null,
                        ] : null,
                        'course' => $certificate->course ? [
                            'id' => $certificate->course->id,
                            'title' => $certificate->course->title,
                            'slug' => $certificate->course->slug,
                            'thumbnail' => $certificate->course->thumbnail ? asset('storage/' . $certificate->course->thumbnail) : null,
                            'description' => $certificate->course->description,
                        ] : null,
                        'exam_response' => $certificate->examResponse ? [
                            'id' => $certificate->examResponse->id,
                            'score' => $certificate->examResponse->score ?? 0,
                            'total_questions' => $certificate->examResponse->total_questions ?? 0,
                            'correct_answers' => $certificate->examResponse->correct_answers ?? 0,
                            'percentage' => $certificate->examResponse->percentage ?? 0.0,
                            'completed_at' => $certificate->examResponse->completed_at?->toISOString(),
                        ] : null,
                        'created_at' => $certificate->created_at?->toISOString(),
                        'updated_at' => $certificate->updated_at?->toISOString(),
                    ];
                } catch (Exception $e) {
                    \Log::error('Error transforming certificate', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
            })->filter(); // Remove null values

            // Calculate additional metadata
            $totalCertificates = Certificate::where('user_id', $user->id)->count();
            $certificatesThisYear = Certificate::where('user_id', $user->id)
                ->whereYear('issue_date', date('Y'))
                ->count();
            $latestCertificateDate = Certificate::where('user_id', $user->id)
                ->orderBy('issue_date', 'desc')
                ->value('issue_date');

            // Prepare response
            $response = [
                'data' => $transformedData,
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
                'from' => $certificates->firstItem(),
                'to' => $certificates->lastItem(),
                'path' => $certificates->path(),
                'first_page_url' => $certificates->url(1),
                'last_page_url' => $certificates->url($certificates->lastPage()),
                'next_page_url' => $certificates->nextPageUrl(),
                'prev_page_url' => $certificates->previousPageUrl(),
                'meta' => [
                    'total_certificates' => $totalCertificates,
                    'certificates_this_year' => $certificatesThisYear,
                    'latest_certificate_date' => $latestCertificateDate?->format('Y-m-d'),
                ]
            ];

            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            \Log::error('Error fetching user certificates', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching your certificates',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/certificates/{id}",
        summary: "Get specific certificate details",
        description: "Returns detailed information about a specific certificate. Users can only access their own certificates.",
        tags: ["Certificates"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Certificate ID",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Certificate details retrieved successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Certificate")
            ),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 403, description: "Forbidden - Not your certificate"),
            new OA\Response(response: 404, description: "Certificate not found"),
            new OA\Response(response: 500, description: "Internal server error")
        ]
    )]
    public function show(Request $request, int $id)
    {
        try {
            $user = $request->user();

            // Fixed relationship name
            $certificate = Certificate::with([
                'user:id,name,email,avatar',
                'course:id,title,slug,thumbnail,description',
                'examResponse:id,score,total_questions,correct_answers,percentage,completed_at'
            ])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

            $response = [
                'id' => $certificate->id,
                'certificate_code' => $certificate->certificate_code,
                'path' => $certificate->path,
                'download_url' => $certificate->path ? asset('storage/' . $certificate->path) : null,
                'issue_date' => $certificate->issue_date?->format('Y-m-d'),
                'user' => $certificate->user ? [
                    'id' => $certificate->user->id,
                    'name' => $certificate->user->name,
                    'email' => $certificate->user->email,
                    'avatar' => $certificate->user->avatar ? asset('storage/' . $certificate->user->avatar) : null,
                ] : null,
                'course' => $certificate->course ? [
                    'id' => $certificate->course->id,
                    'title' => $certificate->course->title,
                    'slug' => $certificate->course->slug,
                    'thumbnail' => $certificate->course->thumbnail ? asset('storage/' . $certificate->course->thumbnail) : null,
                    'description' => $certificate->course->description,
                ] : null,
                'exam_response' => $certificate->examResponse ? [
                    'id' => $certificate->examResponse->id,
                    'score' => $certificate->examResponse->score ?? 0,
                    'total_questions' => $certificate->examResponse->total_questions ?? 0,
                    'correct_answers' => $certificate->examResponse->correct_answers ?? 0,
                    'percentage' => $certificate->examResponse->percentage ?? 0.0,
                    'completed_at' => $certificate->examResponse->completed_at?->toISOString(),
                ] : null,
                'created_at' => $certificate->created_at?->toISOString(),
                'updated_at' => $certificate->updated_at?->toISOString(),
            ];

            return response()->json($response);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Certificate not found or you do not have permission to access it',
                'error' => "Certificate with ID {$id} does not exist or is not accessible"
            ], 404);
        } catch (Exception $e) {
            \Log::error('Error fetching certificate details', [
                'certificate_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching the certificate',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
