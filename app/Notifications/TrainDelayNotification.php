<?php

namespace App\Notifications;

use App\Models\Train\TrainTrip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TrainDelayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TrainTrip $trip,
        public int $delayMinutes
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'fcm'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'train_delay',
            'trip_id' => $this->trip->id,
            'train_id' => $this->trip->train_id,
            'train_number' => $this->trip->train->number,
            'train_name' => $this->trip->train->name,
            'delay_minutes' => $this->delayMinutes,
            'estimated_arrival_time' => $this->trip->estimated_arrival_time?->toISOString(),
            'current_station_id' => $this->trip->current_station_id,
        ];
    }

    public function toFcm($notifiable): array
    {
        $lang = $notifiable->preferred_language ?? 'ar';

        $trainName = is_array($this->trip->train->name)
            ? $this->trip->train->name[$lang]
            : $this->trip->train->name;

        $title = $lang === 'ar'
            ? 'تأخير في رحلتك'
            : 'Train Delay';

        $body = $lang === 'ar'
            ? "القطار {$trainName} متأخر بمقدار {$this->delayMinutes} دقيقة"
            : "Train {$trainName} is delayed by {$this->delayMinutes} minutes";

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'train_delay',
                'trip_id' => $this->trip->id,
                'train_id' => $this->trip->train_id,
                'delay_minutes' => $this->delayMinutes,
            ],
        ];
    }
}
