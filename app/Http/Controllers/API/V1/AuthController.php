<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SocialLogin;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\HasApiTokens;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^01[0125][0-9]{8}$/|unique:users,phone',
            'name' => 'required|string|max:255|min:3',
            'password' => 'required|string|min:8|confirmed',
            'email' => 'nullable|email|unique:users,email',
            'preferred_language' => 'nullable|in:ar,en',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid input', 422, $validator->errors());
        }

        // Create user
        $user = User::create([
            'phone' => $request->phone,
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'email' => $request->email,
            'phone_verified_at' => now(),
            'preferred_language' => $request->preferred_language ?? 'ar',
            'status' => 'active'
        ]);

        // Create token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->apiResponse([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'preferred_language' => $user->preferred_language,
                'ad_free_until' => $user->ad_free_until,
                'reward_points' => $user->reward_points,
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ], 'Registration completed successfully', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^01[0125][0-9]{8}$/',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid input', 422, $validator->errors());
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid phone or password', 401);
        }

        if ($user->status !== 'active') {
            return $this->errorResponse('Your account is not active', 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->apiResponse([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'preferred_language' => $user->preferred_language,
                'ad_free_until' => $user->ad_free_until,
                'reward_points' => $user->reward_points,
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ], 'Login successful', 200);
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
            return $this->errorResponse('Invalid input', 422, $validator->errors());
        }

        if (!in_array($provider, ['google', 'apple', 'facebook'])) {
            return $this->errorResponse('Unsupported social provider', 400);
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

        return $this->apiResponse([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'preferred_language' => $user->preferred_language,
                'ad_free_until' => $user->ad_free_until,
                'reward_points' => $user->reward_points,
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ], 'Social login successful', 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->apiResponse(null, 'Logged out successfully', 200);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->apiResponse([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'preferred_language' => $user->preferred_language,
                'ad_free_until' => $user->ad_free_until,
                'reward_points' => $user->reward_points,
                'created_at' => $user->created_at,
            ]
        ], 'success', 200);
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
            return $this->errorResponse('Invalid input', 422, $validator->errors());
        }

        $user = $request->user();
        $updateData = $request->only(['name', 'email', 'preferred_language']);

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $updateData['avatar'] = $avatarPath;
        }

        $user->update($updateData);
        $user->refresh();

        return $this->apiResponse([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'preferred_language' => $user->preferred_language,
                'ad_free_until' => $user->ad_free_until,
                'reward_points' => $user->reward_points,
            ]
        ], 'Profile updated successfully', 200);
    }
}
