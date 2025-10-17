<?php

namespace App\Events;

use App\Models\Train\TrainTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TrainTrip $trip,
        public string $previousStatus,
        public string $currentStatus
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Trip-specific channel
            new Channel("trips.{$this->trip->id}.status"),

            // Train-specific channel
            new Channel("trains.{$this->trip->train_id}.status"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'train_id' => $this->trip->train_id,
            'train_number' => $this->trip->train->number,
            'train_name' => $this->trip->train->name,
            'previous_status' => $this->previousStatus,
            'current_status' => $this->currentStatus,
            'current_station_id' => $this->trip->current_station_id,
            'next_station_id' => $this->trip->next_station_id,
            'delay_minutes' => $this->trip->delay_minutes,
            'passenger_count' => $this->trip->passenger_count,
            'actual_departure_time' => $this->trip->actual_departure_time?->toISOString(),
            'estimated_arrival_time' => $this->trip->estimated_arrival_time?->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.updated';
    }
}
