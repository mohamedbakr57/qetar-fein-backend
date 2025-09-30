<?php

namespace App\Events;

use App\Models\CommunityMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunityMessagePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(CommunityMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("communities.{$this->message->community->trip_id}.messages"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'user' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                    'avatar' => $this->message->user->avatar,
                ] : null,
                'guest_name' => $this->message->guest_name,
                'station' => $this->message->station,
                'time_passed_minutes' => $this->message->time_passed_minutes,
                'message_type' => $this->message->message_type,
                'additional_data' => $this->message->additional_data,
                'is_verified' => $this->message->is_verified,
                'verification_count' => $this->message->verification_count,
                'created_at' => $this->message->created_at,
            ]
        ];
    }
}