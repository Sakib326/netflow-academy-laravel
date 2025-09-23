<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
     *             @OA\Property(property="discount_amount", type="number", format="float", example=10.00, description="Discount amount to apply"),
     *             @OA\Property(property="notes", type="string", example="Special request", description="Order notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order created successfully"),
     *             @OA\Property(property="order_number", type="string", example="ORD-ABC123"),
     *             @OA\Property(property="amount", type="number", format="float", example=89.99),
     *             @OA\Property(property="status", type="string", example="pending")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Already has pending order or already enrolled"),
     *     @OA\Response(response=404, description="Course not found"),
     *     @OA\Response(response=500, description="Server error")
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
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ]);

        // 5. Calculate pricing
        $discountAmount = $request->input('discount_amount', 0);
        $coursePrice = $course->getEffectivePrice();
        $finalAmount = max(0, $coursePrice - $discountAmount);

        try {
            // 6. Create order
            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $finalAmount,
                'discount_amount' => $discountAmount,
                'status' => 'pending',
                'notes' => $request->input('notes')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully. Please wait for admin approval.',
                'order_number' => $order->order_number,
                'amount' => $finalAmount,
                'status' => 'pending',
                'course_title' => $course->title
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/my-orders",
     *     summary="Get current user's orders",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by order status",
     *         @OA\Schema(type="string", enum={"pending", "paid", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="order_number", type="string", example="ORD-ABC123"),
     *                         @OA\Property(property="amount", type="number", format="float", example=89.99),
     *                         @OA\Property(property="discount_amount", type="number", format="float", example=10.00),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(property="notes", type="string", nullable=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="course", type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Web Development"),
     *                             @OA\Property(property="slug", type="string", example="web-development"),
     *                             @OA\Property(property="thumbnail", type="string", nullable=true)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getMyOrders(Request $request)
    {
        $user = Auth::user();

        $query = Order::where('user_id', $user->id)
            ->with(['course:id,title,slug,thumbnail']);

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
     *     summary="Get specific order details",
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
     *         description="Order details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="ORD-ABC123"),
     *                 @OA\Property(property="amount", type="number", format="float", example=89.99),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="course", type="object"),
     *                 @OA\Property(property="enrollment", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=403, description="Not authorized to view this order")
     * )
     */
    public function getOrderDetails($orderNumber)
    {
        $user = Auth::user();

        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->with(['course', 'enrollment.batch'])
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
            'status' => $order->status,
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'course' => [
                'id' => $order->course->id,
                'title' => $order->course->title,
                'slug' => $order->course->slug,
                'thumbnail' => $order->course->thumbnail ? asset('storage/' . $order->course->thumbnail) : null,
                'price' => $order->course->price,
                'discounted_price' => $order->course->discounted_price,
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
     *     summary="Cancel a pending order",
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
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Order cancelled successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot cancel this order"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function cancelOrder($orderNumber)
    {
        $user = Auth::user();

        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be cancelled'
            ], 400);
        }

        try {
            $order->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/stats",
     *     summary="Get user's order statistics",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Order statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_orders", type="integer", example=5),
     *                 @OA\Property(property="pending_orders", type="integer", example=1),
     *                 @OA\Property(property="paid_orders", type="integer", example=3),
     *                 @OA\Property(property="cancelled_orders", type="integer", example=1),
     *                 @OA\Property(property="total_spent", type="number", format="float", example=299.97),
     *                 @OA\Property(property="active_enrollments", type="integer", example=3)
     *             )
     *         )
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
            'active_enrollments' => Enrollment::where('user_id', $user->id)->where('status', 'active')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
