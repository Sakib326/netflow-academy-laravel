<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Coupon;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CouponController extends Controller
{
    #[OA\Get(
        path: "/api/coupons/check/{slug}",
        summary: "Check coupon validity and get final amount for a course",
        description: "Checks if a coupon is valid for the given course slug and returns discount details and final amount.",
        tags: ["Coupons"],
        parameters: [
            new OA\Parameter(
                name: "slug",
                in: "path",
                required: true,
                description: "Course slug",
                schema: new OA\Schema(type: "string", example: "web-development-fundamentals")
            ),
            new OA\Parameter(
                name: "coupon_code",
                in: "query",
                required: true,
                description: "Coupon code to check",
                schema: new OA\Schema(type: "string", example: "SAVE20")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Coupon checked successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Coupon applied successfully"),
                        new OA\Property(property: "discount_amount", type: "number", format: "float", example: 20.00),
                        new OA\Property(property: "final_amount", type: "number", format: "float", example: 80.00),
                        new OA\Property(property: "course_price", type: "number", format: "float", example: 100.00),
                        new OA\Property(property: "coupon_code", type: "string", example: "SAVE20"),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Invalid coupon or not applicable"
            ),
            new OA\Response(
                response: 404,
                description: "Course not found"
            )
        ]
    )]
    public function check(Request $request, $slug)
    {
        $couponCode = $request->query('coupon_code');
        if (!$couponCode) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon code is required'
            ], 400);
        }

        $course = Course::where('slug', $slug)->where('status', 'published')->first();
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        }

        $coupon = Coupon::where('code', strtoupper($couponCode))->first();
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code'
            ], 400);
        }

        $coursePrice = $course->getEffectivePrice();
        if (!$coupon->isValidForCourse($course->id, $coursePrice)) {
            return response()->json([
                'success' => false,
                'message' => $coupon->getValidationMessage($coursePrice, $course->id)
            ], 400);
        }

        $discountAmount = $coupon->getDiscountAmount($coursePrice);
        $finalAmount = $coupon->getFinalAmount($coursePrice);

        return response()->json([
            'success' => true,
            'message' => $coupon->getValidationMessage($coursePrice, $course->id),
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'course_price' => $coursePrice,
            'coupon_code' => $coupon->code,
        ]);
    }
}
