<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpVerification;
use App\Models\SocialLogin;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\HasApiTokens;

class AuthController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^(01[0125][0-9]{8}|(\+2|002)01[0125][0-9]{8})$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid phone number format',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;

        // Check rate limiting (max 3 attempts per hour)
        $recentAttempts = OtpVerification::where('phone', $phone)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($recentAttempts >= 3) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many OTP requests. Please try again later.',
                'retry_after' => 3600
            ], 429);
        }

        $result = $this->otpService->sendOtp($phone);

        if ($result['success']) {
            return response()->json([
                'status' => 'success',
                'message' => 'OTP sent successfully',
                'otp' => $result['otp_code'], // Only in debug mode
                'expires_in' => 300 // 5 minutes
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send OTP. Please try again.'
        ], 500);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^(01[0125][0-9]{8}|(\+2|002)01[0125][0-9]{8})$/',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $verification = $this->otpService->verifyOtp($request->phone, $request->otp_code);

        if (!$verification['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $verification['message']
            ], 400);
        }

        // Find or create user
        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            $user = User::create([
                'phone' => $request->phone,
                'phone_verified_at' => now(),
                'preferred_language' => 'ar',
                'status' => 'active'
            ]);
        } else {
            $user->update(['phone_verified_at' => now()]);
        }

        // Create token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Phone verified successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'preferred_language' => $user->preferred_language,
                    'ad_free_until' => $user->ad_free_until,
                    'reward_points' => $user->reward_points,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function socialLogin(Request $request, string $provider): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|string',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
            'avatar' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!in_array($provider, ['google', 'apple', 'facebook'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unsupported social provider'
            ], 400);
        }

        // Check if social login exists
        $socialLogin = SocialLogin::where('provider', $provider)
            ->where('provider_id', $request->provider_id)
            ->first();

        if ($socialLogin) {
            $user = $socialLogin->user;
        } else {
            // Find user by email or create new
            $user = null;
            if ($request->email) {
                $user = User::where('email', $request->email)->first();
            }

            if (!$user) {
                $user = User::create([
                    'email' => $request->email,
                    'name' => $request->name,
                    'avatar' => $request->avatar,
                    'preferred_language' => 'ar',
                    'status' => 'active',
                    'email_verified_at' => now()
                ]);
            }

            // Create social login record
            SocialLogin::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $request->provider_id,
            ]);
        }

        // Create token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Social login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'preferred_language' => $user->preferred_language,
                    'ad_free_until' => $user->ad_free_until,
                    'reward_points' => $user->reward_points,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'preferred_language' => $user->preferred_language,
                    'ad_free_until' => $user->ad_free_until,
                    'reward_points' => $user->reward_points,
                    'created_at' => $user->created_at,
                ]
            ]
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $request->user()->id,
            'preferred_language' => 'nullable|in:ar,en',
            'avatar' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $updateData = $request->only(['name', 'email', 'preferred_language']);

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $updateData['avatar'] = $avatarPath;
        }

        $user->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'preferred_language' => $user->preferred_language,
                    'ad_free_until' => $user->ad_free_until,
                    'reward_points' => $user->reward_points,
                ]
            ]
        ]);
    }
}