<?php

namespace App\Notifications;

use App\Models\CommunityMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CommunityMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CommunityMessage $message
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'fcm'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'community_mention',
            'message_id' => $this->message->id,
            'community_id' => $this->message->community_id,
            'trip_id' => $this->message->community->trip_id,
            'message_type' => $this->message->message_type,
        ];
    }

    public function toFcm($notifiable): array
    {
        $lang = $notifiable->preferred_language ?? 'ar';

        $title = $lang === 'ar'
            ? 'تحديث في المجتمع'
            : 'Community Update';

        $body = $lang === 'ar'
            ? 'هناك تحديث جديد في رحلتك'
            : 'New update in your trip community';

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'community_mention',
                'message_id' => $this->message->id,
                'trip_id' => $this->message->community->trip_id,
            ],
        ];
    }
}
