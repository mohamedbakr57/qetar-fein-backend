<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API V1 Routes
Route::prefix('v1')->group(function () {

    // Authentication Routes (Public)
    Route::prefix('auth')->group(function () {
        Route::post('/phone/send-otp', [App\Http\Controllers\API\V1\AuthController::class, 'sendOtp']);
        Route::post('/phone/verify', [App\Http\Controllers\API\V1\AuthController::class, 'verifyOtp']);
        Route::post('/register/complete', [App\Http\Controllers\API\V1\AuthController::class, 'completeRegistration']);
        Route::post('/social/{provider}', [App\Http\Controllers\API\V1\AuthController::class, 'socialLogin']);
    });

    // Public Routes (No authentication required)
    Route::group([], function () {
        // Stations
        Route::get('/stations', [App\Http\Controllers\API\V1\StationController::class, 'index']);
        Route::get('/stations/{id}', [App\Http\Controllers\API\V1\StationController::class, 'show']);
        Route::get('/stations/{id}/departures', [App\Http\Controllers\API\V1\StationController::class, 'departures']);
        Route::get('/stations/{id}/arrivals', [App\Http\Controllers\API\V1\StationController::class, 'arrivals']);

        // Trains
        Route::get('/trains', [App\Http\Controllers\API\V1\TrainController::class, 'index']);
        Route::get('/trains/{id}', [App\Http\Controllers\API\V1\TrainController::class, 'show']);
        Route::get('/trains/{id}/schedule', [App\Http\Controllers\API\V1\TrainController::class, 'schedule']);
        Route::get('/trains/{id}/location', [App\Http\Controllers\API\V1\TrainController::class, 'location']);
        Route::get('/trains/live', [App\Http\Controllers\API\V1\TrainController::class, 'liveTrains']);

        // Community (Read-only for guests)
        Route::get('/communities/{tripId}', [App\Http\Controllers\API\V1\CommunityController::class, 'show']);
        Route::get('/communities/{tripId}/messages', [App\Http\Controllers\API\V1\CommunityController::class, 'messages']);
        Route::get('/message-types', [App\Http\Controllers\API\V1\CommunityController::class, 'getMessageTypes']);

        // Badges (Public viewing)
        Route::get('/badges', [App\Http\Controllers\API\V1\RewardController::class, 'allBadges']);
        Route::get('/badges/{id}', [App\Http\Controllers\API\V1\RewardController::class, 'badgeDetails']);
        Route::get('/leaderboard', [App\Http\Controllers\API\V1\RewardController::class, 'leaderboard']);
    });

    // Protected Routes (Authentication required)
    Route::middleware('auth:sanctum')->group(function () {

        // Authentication
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [App\Http\Controllers\API\V1\AuthController::class, 'logout']);
            Route::get('/profile', [App\Http\Controllers\API\V1\AuthController::class, 'profile']);
            Route::put('/profile', [App\Http\Controllers\API\V1\AuthController::class, 'updateProfile']);
        });

        // Passenger Assignments
        Route::prefix('assignments')->group(function () {
            Route::get('/', [App\Http\Controllers\API\V1\AssignmentController::class, 'index']);
            Route::post('/', [App\Http\Controllers\API\V1\AssignmentController::class, 'store']);
            Route::get('/active', [App\Http\Controllers\API\V1\AssignmentController::class, 'activeAssignment']);
            Route::get('/{id}', [App\Http\Controllers\API\V1\AssignmentController::class, 'show']);
            Route::put('/{id}/location', [App\Http\Controllers\API\V1\AssignmentController::class, 'updateLocation']);
            Route::post('/{id}/enable-location', [App\Http\Controllers\API\V1\AssignmentController::class, 'enableLocationSharing']);
            Route::post('/{id}/disable-location', [App\Http\Controllers\API\V1\AssignmentController::class, 'disableLocationSharing']);
            Route::post('/{id}/complete', [App\Http\Controllers\API\V1\AssignmentController::class, 'complete']);
            Route::post('/{id}/cancel', [App\Http\Controllers\API\V1\AssignmentController::class, 'cancel']);
        });

        // Community (Write access for authenticated users)
        Route::prefix('communities')->group(function () {
            Route::post('/{tripId}/messages', [App\Http\Controllers\API\V1\CommunityController::class, 'postMessage']);
            Route::post('/{tripId}/messages/{messageId}/verify', [App\Http\Controllers\API\V1\CommunityController::class, 'verifyMessage']);
        });

        // Rewards & Gamification
        Route::prefix('rewards')->group(function () {
            Route::get('/my', [App\Http\Controllers\API\V1\RewardController::class, 'myRewards']);
            Route::get('/summary', [App\Http\Controllers\API\V1\RewardController::class, 'rewardsSummary']);
        });

        Route::prefix('badges')->group(function () {
            Route::get('/my', [App\Http\Controllers\API\V1\RewardController::class, 'myBadges']);
        });
    });

    // Optional Authentication Routes (Work for both guests and authenticated users)
    Route::middleware('auth:sanctum')->group(function () {
        // These routes can handle both authenticated and guest users
        // The controllers will check if user is authenticated
    });
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'available_versions' => ['v1']
    ], 404);
});