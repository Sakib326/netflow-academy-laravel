<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zoom;
use OpenApi\Attributes as OA;

class ZoomController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/zoom/latest",
     *     summary="Get the latest created Zoom link",
     *     description="Returns the most recently created Zoom meeting link",
     *     tags={"Zoom"},
     *     @OA\Response(
     *         response=200,
     *         description="Latest Zoom link retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="link", type="string", example="https://zoom.us/j/123456789"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-12-01T10:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No Zoom links found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No Zoom links available")
     *         )
     *     )
     * )
     */
    public function getLatest()
    {
        $latestZoom = Zoom::latest('created_at')->first();

        if (!$latestZoom) {
            return response()->json([
                'success' => false,
                'message' => 'No Zoom links available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $latestZoom->id,
                'link' => $latestZoom->link,
                'created_at' => $latestZoom->created_at
            ]
        ]);
    }
}
