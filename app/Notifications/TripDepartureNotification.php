<?php

namespace App\Notifications;

use App\Models\Train\TrainTrip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TripDepartureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TrainTrip $trip,
        public int $minutesUntilDeparture
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'fcm'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'trip_departure',
            'trip_id' => $this->trip->id,
            'train_id' => $this->trip->train_id,
            'train_number' => $this->trip->train->number,
            'train_name' => $this->trip->train->name,
            'minutes_until_departure' => $this->minutesUntilDeparture,
            'departure_time' => $this->trip->estimated_departure_time?->toISOString(),
        ];
    }

    public function toFcm($notifiable): array
    {
        $lang = $notifiable->preferred_language ?? 'ar';

        $trainName = is_array($this->trip->train->name)
            ? $this->trip->train->name[$lang]
            : $this->trip->train->name;

        $title = $lang === 'ar'
            ? 'تنبيه مغادرة القطار'
            : 'Train Departure Alert';

        $body = $lang === 'ar'
            ? "القطار {$trainName} سيغادر خلال {$this->minutesUntilDeparture} دقيقة"
            : "Train {$trainName} departs in {$this->minutesUntilDeparture} minutes";

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'trip_departure',
                'trip_id' => $this->trip->id,
                'train_id' => $this->trip->train_id,
                'minutes_until_departure' => $this->minutesUntilDeparture,
            ],
        ];
    }
}
