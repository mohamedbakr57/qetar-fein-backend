<?php

namespace App\Events;

use App\Models\PassengerAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PassengerLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PassengerAssignment $assignment
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Trip-specific channel for aggregating passenger locations
            new Channel("trips.{$this->assignment->trip_id}.passengers"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'trip_id' => $this->assignment->trip_id,
            'location' => [
                'latitude' => $this->assignment->current_latitude,
                'longitude' => $this->assignment->current_longitude,
                'accuracy' => $this->assignment->location_accuracy,
                'speed_kmh' => $this->assignment->speed_kmh,
                'heading' => $this->assignment->heading,
            ],
            'boarding_station_id' => $this->assignment->boarding_station_id,
            'destination_station_id' => $this->assignment->destination_station_id,
            'updated_at' => $this->assignment->last_location_update?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'passenger.location.updated';
    }
}
