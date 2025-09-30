<?php

namespace App\Events;

use App\Models\Train\Train;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Train $train,
        public array $location,
        public ?int $delayMinutes = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Public channel for all users tracking this train
            new Channel("trains.{$this->train->id}.location"),

            // General channel for live train updates
            new Channel('trains.live.updates'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'train_id' => $this->train->id,
            'train_number' => $this->train->number,
            'train_name' => $this->train->getTranslations('name'),
            'location' => [
                'latitude' => $this->location['latitude'],
                'longitude' => $this->location['longitude'],
                'speed_kmh' => $this->location['speed_kmh'] ?? 0,
                'heading' => $this->location['heading'] ?? null,
                'accuracy' => $this->location['accuracy'] ?? null,
            ],
            'delay_minutes' => $this->delayMinutes ?? 0,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }
}
