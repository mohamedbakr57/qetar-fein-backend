<?php

namespace App\Events;

use App\Models\Train\TrainTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainDelayUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TrainTrip $trip,
        public int $previousDelay,
        public int $currentDelay
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Trip-specific channel
            new Channel("trips.{$this->trip->id}.delay"),

            // Train-specific channel (for all trips of this train)
            new Channel("trains.{$this->trip->train_id}.delay"),

            // General delays channel
            new Channel('trains.delays'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'train_id' => $this->trip->train_id,
            'train_number' => $this->trip->train->number,
            'train_name' => $this->trip->train->name,
            'previous_delay_minutes' => $this->previousDelay,
            'current_delay_minutes' => $this->currentDelay,
            'delay_change' => $this->currentDelay - $this->previousDelay,
            'status' => $this->trip->status,
            'current_station_id' => $this->trip->current_station_id,
            'estimated_arrival_time' => $this->trip->estimated_arrival_time?->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delay.updated';
    }
}
