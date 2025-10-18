<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API V1 Routes
Route::prefix('v1')->group(function () {

    // Authentication Routes (Public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [App\Http\Controllers\API\V1\AuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\API\V1\AuthController::class, 'login']);
        Route::post('/social/{provider}', [App\Http\Controllers\API\V1\AuthController::class, 'socialLogin']);
    });

    // Public Routes (No authentication required)
    Route::group([], function () {
        // Stations
        Route::get('/stations', [App\Http\Controllers\API\V1\StationController::class, 'index']);
        Route::get('/stations/popular', [App\Http\Controllers\API\V1\StationController::class, 'popular']);
        Route::get('/stations/{id}', [App\Http\Controllers\API\V1\StationController::class, 'show']);
        Route::get('/stations/{id}/departures', [App\Http\Controllers\API\V1\StationController::class, 'departures']);
        Route::get('/stations/{id}/arrivals', [App\Http\Controllers\API\V1\StationController::class, 'arrivals']);

        // Trains
        Route::get('/trains', [App\Http\Controllers\API\V1\TrainController::class, 'index']);
        Route::post('/trains/search', [App\Http\Controllers\API\V1\TrainController::class, 'search']);
        Route::get('/trains/{id}', [App\Http\Controllers\API\V1\TrainController::class, 'show']);
        Route::get('/trains/{id}/schedule', [App\Http\Controllers\API\V1\TrainController::class, 'schedule']);
        Route::get('/trains/{id}/location', [App\Http\Controllers\API\V1\TrainController::class, 'location']);
        Route::get('/trains/live', [App\Http\Controllers\API\V1\TrainController::class, 'liveTrains']);

        // Train Types
        Route::get('/train-types', [App\Http\Controllers\API\V1\TrainTypeController::class, 'index']);
        Route::get('/train-types/{id}', [App\Http\Controllers\API\V1\TrainTypeController::class, 'show']);

        // Trips
        Route::get('/trips/{tripId}/estimate-delay', [App\Http\Controllers\API\V1\TripController::class, 'estimateDelay']);

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
            Route::get('/{id}/progress', [App\Http\Controllers\API\V1\AssignmentController::class, 'tripProgress']);
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

        // User Stats
        Route::get('/user/stats', [App\Http\Controllers\API\V1\UserStatsController::class, 'index']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [App\Http\Controllers\API\V1\NotificationController::class, 'index']);
            Route::post('/fcm-token', [App\Http\Controllers\API\V1\NotificationController::class, 'updateFcmToken']);
            Route::delete('/fcm-token', [App\Http\Controllers\API\V1\NotificationController::class, 'removeFcmToken']);
            Route::get('/preferences', [App\Http\Controllers\API\V1\NotificationController::class, 'getPreferences']);
            Route::put('/preferences', [App\Http\Controllers\API\V1\NotificationController::class, 'updatePreferences']);
            Route::post('/{notificationId}/read', [App\Http\Controllers\API\V1\NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [App\Http\Controllers\API\V1\NotificationController::class, 'markAllAsRead']);
            Route::delete('/{notificationId}', [App\Http\Controllers\API\V1\NotificationController::class, 'destroy']);
            Route::post('/test', [App\Http\Controllers\API\V1\NotificationController::class, 'test']);
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