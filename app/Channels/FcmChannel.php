<?php

namespace App\Channels;

use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Send the given notification
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!$notifiable->fcm_token) {
            return;
        }

        $fcmData = $notification->toFcm($notifiable);

        $this->pushService->sendNotification(
            $notifiable->fcm_token,
            $fcmData['title'],
            $fcmData['body'],
            $fcmData['data'] ?? []
        );
    }
}
