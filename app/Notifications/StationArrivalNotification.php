<?php

namespace App\Notifications;

use App\Models\Train\TrainTrip;
use App\Models\Train\Station;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StationArrivalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TrainTrip $trip,
        public Station $station
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'fcm'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'station_arrival',
            'trip_id' => $this->trip->id,
            'train_id' => $this->trip->train_id,
            'train_number' => $this->trip->train->number,
            'station_id' => $this->station->id,
            'station_name' => $this->station->name,
        ];
    }

    public function toFcm($notifiable): array
    {
        $lang = $notifiable->preferred_language ?? 'ar';

        $trainName = is_array($this->trip->train->name)
            ? $this->trip->train->name[$lang]
            : $this->trip->train->name;

        $stationName = is_array($this->station->name)
            ? $this->station->name[$lang]
            : $this->station->name;

        $title = $lang === 'ar'
            ? 'وصول إلى المحطة'
            : 'Arrival at Station';

        $body = $lang === 'ar'
            ? "القطار {$trainName} وصل إلى محطة {$stationName}"
            : "Train {$trainName} has arrived at {$stationName} station";

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'station_arrival',
                'trip_id' => $this->trip->id,
                'station_id' => $this->station->id,
            ],
        ];
    }
}
