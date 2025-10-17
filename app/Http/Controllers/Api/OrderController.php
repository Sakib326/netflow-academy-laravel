<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Order;
use App\Models\Enrollment;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/orders/create/{slug}",
     *     summary="Create a new order for a course",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Course slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Special request", description="Order notes"),
     *             @OA\Property(property="coupon_code", type="string", example="SAVE20", description="Optional coupon code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully"
     *     )
     * )
     */
    public function createOrder(Request $request, $slug)
    {
        $user = Auth::user();

        // 1. Validate course exists
        $course = Course::where('slug', $slug)->where('status', 'published')->first();
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found or not available'
            ], 404);
        }

        // 2. Check if user already has pending order for this course
        $existingOrder = Order::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->first();

        if ($existingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending order for this course',
                'order_number' => $existingOrder->order_number
            ], 400);
        }

        // 3. Check if already enrolled
        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->whereHas('batch', function ($q) use ($course) {
                $q->where('course_id', $course->id);
            })
            ->where('status', 'active')
            ->exists();

        if ($isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You are already enrolled in this course'
            ], 400);
        }

        // 4. Validate request
        $request->validate([
            'notes' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|max:50'
        ]);

        // 5. Calculate pricing from DB
        $coursePrice = $course->getEffectivePrice();
        $discountAmount = 0;

        if ($course->discound_price && $course->discound_price < $course->price) {
            $discountAmount = $course->price - $course->discound_price;
            $finalAmount = $course->discound_price;
        } else {
            $finalAmount = $course->price;
        }

        // 6. Handle coupon validation and application
        $coupon = null;
        $couponDiscount = 0;
        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

            if ($coupon && $coupon->isValidForCourse($course->id, $course->price)) {
                $couponDiscount = $coupon->getDiscountAmount($course->price);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $coupon ? $coupon->getValidationMessage($course->price, $course->id) : 'Invalid coupon code'
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            // 7. Create order
            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $course->price,
                'discount_amount' => $course->discound_price,
                'coupon_id' => $coupon ? $coupon->id : null,
                'coupon_discount' => $couponDiscount,
                'status' => 'pending',
                'notes' => $request->input('notes')
            ]);

            // 8. Mark coupon as used if applied
            if ($coupon) {
                $coupon->markAsUsed();
            }

            DB::commit();

            // Calculate final amount with all discounts
            $finalAmountWithCoupon = $order->getFinalAmount();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully. Please wait for admin approval.',
                'order_number' => $order->order_number,
                'amount' => $course->price,
                'discount_amount' => $course->discound_price,
                'coupon_discount' => $couponDiscount,
                'final_amount' => $finalAmountWithCoupon,
                'status' => 'pending',
                'course_title' => $course->title
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/my-orders",
     *     summary="Get user's orders",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by order status",
     *         @OA\Schema(type="string", enum={"pending", "paid", "cancelled"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully"
     *     )
     * )
     */
    public function getMyOrders(Request $request)
    {
        $user = Auth::user();

        $query = Order::where('user_id', $user->id)
            ->with(['course:id,title,slug,thumbnail', 'coupon:id,code']);

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        // Transform the data
        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->amount,
                'discount_amount' => $order->discount_amount,
                'coupon_discount' => $order->coupon_discount,
                'final_amount' => $order->getFinalAmount(),
                'coupon_code' => $order->coupon ? $order->coupon->code : null,
                'status' => $order->status,
                'notes' => $order->notes,
                'created_at' => $order->created_at,
                'course' => [
                    'id' => $order->course->id,
                    'title' => $order->course->title,
                    'slug' => $order->course->slug,
                    'thumbnail' => $order->course->thumbnail ? asset('storage/' . $order->course->thumbnail) : null,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{orderNumber}",
     *     summary="Get order details by order number",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="orderNumber",
     *         in="path",
     *         required=true,
     *         description="Order number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details retrieved successfully"
     *     )
     * )
     */
    public function getOrderDetails($orderNumber)
    {
        $user = Auth::user();

        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->with(['course', 'enrollment.batch', 'coupon'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->amount,
            'discount_amount' => $order->discount_amount,
            'coupon_discount' => $order->coupon_discount,
            'final_amount' => $order->getFinalAmount(),
            'coupon_code' => $order->coupon ? $order->coupon->code : null,
            'status' => $order->status,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'course' => [
                'id' => $order->course->id,
                'title' => $order->course->title,
                'slug' => $order->course->slug,
                'thumbnail' => $order->course->thumbnail ? asset('storage/' . $order->course->thumbnail) : null,
                'price' => $order->course->price,
                'discounted_price' => $order->course->discound_price,
                'course_type' => $order->course->course_type,
            ],
            'enrollment' => null
        ];

        if ($order->enrollment) {
            $orderData['enrollment'] = [
                'id' => $order->enrollment->id,
                'status' => $order->enrollment->status,
                'enrolled_at' => $order->enrollment->created_at,
                'batch_name' => $order->enrollment->batch->name ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/orders/{orderNumber}/cancel",
     *     summary="Cancel an order",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="orderNumber",
     *         in="path",
     *         required=true,
     *         description="Order number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully"
     *     )
     * )
     */
    public function cancelOrder($orderNumber)
    {
        $user = Auth::user();

        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('coupon')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or cannot be cancelled'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update order status
            $order->status = 'cancelled';
            $order->save();

            // Decrease coupon usage count if coupon was used
            if ($order->coupon) {
                $order->coupon->decrement('used_count');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/stats",
     *     summary="Get order statistics for the authenticated user",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Order statistics retrieved successfully"
     *     )
     * )
     */
    public function getOrderStats()
    {
        $user = Auth::user();

        $stats = [
            'total_orders' => Order::where('user_id', $user->id)->count(),
            'pending_orders' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
            'paid_orders' => Order::where('user_id', $user->id)->where('status', 'paid')->count(),
            'cancelled_orders' => Order::where('user_id', $user->id)->where('status', 'cancelled')->count(),
            'total_spent' => Order::where('user_id', $user->id)->where('status', 'paid')->sum('amount'),
            'total_savings' => Order::where('user_id', $user->id)->where('status', 'paid')->sum('discount_amount'),
            'coupon_savings' => Order::where('user_id', $user->id)->where('status', 'paid')->sum('coupon_discount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/orders/{id}/approve",
     *     summary="Approve an order (Admin only)",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order approved successfully"
     *     )
     * )
     */
    public function approveOrder($id)
    {
        $order = Order::with(['user', 'course'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be approved'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Update order status
            $order->status = 'paid';
            $order->save();

            // Create enrollment if course has batches
            $batch = $order->course->batches()->where('status', 'active')->first();
            if ($batch) {
                Enrollment::create([
                    'user_id' => $order->user_id,
                    'batch_id' => $batch->id,
                    'order_id' => $order->id,
                    'status' => 'active'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order approved successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/id/{id}",
     *     summary="Get order by ID (Admin)",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details retrieved successfully"
     *     )
     * )
     */
    public function getOrderById($id)
    {
        $order = Order::with(['course', 'enrollment.batch', 'user', 'coupon'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $customer = $order->user ? [
            'id' => $order->user->id,
            'name' => $order->user->name,
            'email' => $order->user->email,
            'avatar' => $order->user->avatar ? asset('storage/' . $order->user->avatar) : null,
            'created_at' => $order->user->created_at,
        ] : null;

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->amount,
            'discount_amount' => $order->discount_amount,
            'coupon_discount' => $order->coupon_discount,
            'final_amount' => $order->getFinalAmount(),
            'coupon_code' => $order->coupon ? $order->coupon->code : null,
            'status' => $order->status,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'course' => $order->course ? [
                'id' => $order->course->id,
                'title' => $order->course->title,
                'slug' => $order->course->slug,
                'thumbnail' => $order->course->thumbnail ? asset('storage/' . $order->course->thumbnail) : null,
                'price' => $order->course->price,
                'discounted_price' => $order->course->discounted_price ?? $order->course->discound_price ?? null,
            ] : null,
            'enrollment' => $order->enrollment ? [
                'id' => $order->enrollment->id,
                'status' => $order->enrollment->status,
                'enrolled_at' => $order->enrollment->created_at,
                'batch_name' => $order->enrollment->batch->name ?? null,
            ] : null,
            'customer' => $customer,
        ];

        return response()->json([
            'success' => true,
            'data' => $orderData
        ]);
    }
}
