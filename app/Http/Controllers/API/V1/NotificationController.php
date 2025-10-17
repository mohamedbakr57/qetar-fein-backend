<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Update FCM token for push notifications
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|in:ios,android',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid input', 422, $validator->errors());
        }

        $user = $request->user();

        $user->update([
            'fcm_token' => $request->fcm_token,
            'fcm_token_updated_at' => now(),
        ]);

        return $this->apiResponse(null, 'FCM token updated successfully');
    }

    /**
     * Remove FCM token (on logout or device change)
     */
    public function removeFcmToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'fcm_token' => null,
            'fcm_token_updated_at' => null,
        ]);

        return $this->apiResponse(null, 'FCM token removed successfully');
    }

    /**
     * Get notification preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = $user->notification_preferences ?? [
            'train_delays' => true,
            'trip_departures' => true,
            'station_arrivals' => true,
            'community_messages' => true,
            'system_updates' => true,
        ];

        return $this->apiResponse(['preferences' => $preferences]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'train_delays' => 'nullable|boolean',
            'trip_departures' => 'nullable|boolean',
            'station_arrivals' => 'nullable|boolean',
            'community_messages' => 'nullable|boolean',
            'system_updates' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid input', 422, $validator->errors());
        }

        $user = $request->user();

        $currentPreferences = $user->notification_preferences ?? [];

        $updatedPreferences = array_merge($currentPreferences, $request->only([
            'train_delays',
            'trip_departures',
            'station_arrivals',
            'community_messages',
            'system_updates',
        ]));

        $user->update(['notification_preferences' => $updatedPreferences]);

        return $this->apiResponse(['preferences' => $updatedPreferences], 'Preferences updated successfully');
    }

    /**
     * Get user notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->apiResponse([
            'notifications' => $notifications->items(),
            'unread_count' => $user->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->find($notificationId);

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        $notification->markAsRead();

        return $this->apiResponse(null, 'Notification marked as read');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->unreadNotifications->markAsRead();

        return $this->apiResponse(null, 'All notifications marked as read');
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->find($notificationId);

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        $notification->delete();

        return $this->apiResponse(null, 'Notification deleted');
    }

    /**
     * Test notification (for development)
     */
    public function test(Request $request): JsonResponse
    {
        if (!app()->environment('local', 'development')) {
            return $this->errorResponse('Test notifications only available in development', 403);
        }

        $user = $request->user();

        if (!$user->fcm_token) {
            return $this->errorResponse('No FCM token registered', 400);
        }

        $pushService = app(\App\Services\PushNotificationService::class);

        $result = $pushService->sendToUser(
            $user,
            'Test Notification',
            'This is a test notification from Qatar Fein',
            ['type' => 'test', 'timestamp' => now()->toISOString()]
        );

        return $this->apiResponse([
            'sent' => $result,
            'fcm_token' => substr($user->fcm_token, 0, 20) . '...',
        ], $result ? 'Test notification sent successfully' : 'Failed to send test notification');
    }
}
