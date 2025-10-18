<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Exception;
use OpenApi\Attributes as OA;

class CertificateController extends Controller
{
    #[OA\Get(
        path: "/api/certificates/my-certificates",
        summary: "Get user's certificates with pagination",
        tags: ["Certificates"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", maximum: 50)),
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 500, description: "Server Error")
        ]
    )]
    public function getMyCertificates(Request $request)
    {
        try {
            $user = $request->user();
            $perPage = min((int)$request->get('per_page', 10), 50);

            $query = Certificate::where('user_id', $user->id)
                ->with(['user:id,name,email', 'course:id,title,slug'])
                ->latest('issue_date');

            // Search filter
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('certificate_code', 'like', "%{$search}%")
                      ->orWhereHas('course', fn ($q) => $q->where('title', 'like', "%{$search}%"));
                });
            }

            $certificates = $query->paginate($perPage);

            return response()->json($certificates);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error fetching certificates',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/certificates/{id}",
        summary: "Get specific certificate",
        tags: ["Certificates"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 404, description: "Not Found")
        ]
    )]
    public function show(Request $request, int $id)
    {
        try {
            $certificate = Certificate::with(['user:id,name,email', 'course:id,title,slug'])
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            return response()->json($certificate);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Certificate not found'
            ], 404);
        }
    }
}
