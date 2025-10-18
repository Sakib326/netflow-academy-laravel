<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use App\Mail\PasswordResetCodeMail;

#[OA\Info(
    version: "1.0.0",
    title: "Netflow Academy API",
    description: "API documentation for Netflow Academy Learning Management System"
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local development server"
)]
#[OA\Server(
    url: "https://admin.netflowacademy.com",
    description: "Production server"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "Token"
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/auth/register",
        summary: "Register a new user",
        description: "Create a new user account",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "phone", type: "string", example: "+8801234567890"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User registered successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "User registered successfully"),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "token", type: "string"),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation errors"
            )
        ]
    )]
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'student', // Default role
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    #[OA\Post(
        path: "/api/auth/login",
        summary: "User login",
        description: "Authenticate user and return API token",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Login successful"),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                        new OA\Property(property: "token", type: "string"),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Invalid credentials"
            )
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact support.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    #[OA\Get(
        path: "/api/auth/me",
        summary: "Get current user",
        description: "Get authenticated user details",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "User details",
                content: new OA\JsonContent(ref: "#/components/schemas/User")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized"
            )
        ]
    )]
    public function me(Request $request)
    {
        $user = $request->user();

        // Add full URL for avatar
        $userArray = $user->toArray();
        if ($user->avatar) {
            $userArray['avatar_url'] = asset('storage/' . $user->avatar);
        } else {
            $userArray['avatar_url'] = null;
        }

        return response()->json($userArray);
    }

    #[OA\Post(
        path: "/api/auth/update",
        summary: "Update user profile",
        description: "Update authenticated user profile with form data including avatar upload",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "name", type: "string", example: "John Doe"),
                        new OA\Property(property: "phone", type: "string", example: "+8801234567890"),
                        new OA\Property(
                            property: "avatar",
                            type: "string",
                            format: "binary",
                            description: "Avatar image file (jpg, jpeg, png, gif, webp - max 2MB)"
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Profile updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Profile updated successfully"),
                        new OA\Property(property: "user", ref: "#/components/schemas/User"),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation errors"
            )
        ]
    )]
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048', // 2MB max
        ]);

        $user = $request->user();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        // Update other fields
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        $user->save();

        // Add full URL for avatar
        $userArray = $user->toArray();
        if ($user->avatar) {
            $userArray['avatar_url'] = Storage::disk('public')->url($user->avatar);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $userArray,
        ]);
    }

    #[OA\Post(
        path: "/api/auth/update-password",
        summary: "Update password",
        description: "Update authenticated user password",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", format: "password", example: "oldpassword"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Password updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Password updated successfully"),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation errors"
            )
        ]
    )]
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    #[OA\Post(
        path: "/api/auth/forgot-password",
        summary: "Forgot password",
        description: "Send a 6-digit password reset code to the user's email.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com")]
            )
        ),
        responses: [new OA\Response(response: 200, description: "Reset code sent")]
    )]
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        // Generate a 6-digit code
        $resetCode = (string) random_int(100000, 999999);

        // Save the code and expiration date (e.g., 10 minutes from now)
        $user->update([
            'password_reset_code' => $resetCode,
            'password_reset_expires_at' => now()->addMinutes(10)
        ]);

        // Send the email using the Mailable class
        Mail::to($user->email)->send(new PasswordResetCodeMail($user, $resetCode));

        return response()->json(['message' => 'A password reset code has been sent to your email.']);
    }

    #[OA\Post(
        path: "/api/auth/reset-password",
        summary: "Reset password with code",
        description: "Reset user password using the 6-digit code from the email.",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "code", "password", "password_confirmation"],
                properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "john@example.com"),
                new OA\Property(property: "code", type: "string", example: "123456", description: "The 6-digit code from the email."),
                new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"),
                new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123"),
                ]
            )
        ),
        responses: [
        new OA\Response(response: 200, description: "Password has been reset successfully."),
        new OA\Response(response: 422, description: "Invalid code or validation errors.")
        ]
    )]
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if the code is valid and not expired
        if (!$user->password_reset_code ||
            $user->password_reset_code !== $request->code ||
            $user->password_reset_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['The reset code is invalid or has expired.'],
            ]);
        }

        // Update the password
        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_code' => null, // Clear the code
            'password_reset_expires_at' => null,
        ]);

        return response()->json(['message' => 'Your password has been reset successfully.']);
    }

    #[OA\Post(
        path: "/api/auth/logout",
        summary: "User logout",
        description: "Logout user and revoke current token",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Logout successful",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Logout successful"),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized"
            )
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
