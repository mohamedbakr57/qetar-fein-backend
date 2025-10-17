<?php

namespace App\Events;

use App\Models\CommunityMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageVerificationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CommunityMessage $message,
        public int $confirmations,
        public int $disputes
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("communities.{$this->message->community->trip_id}.messages"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'verification_count' => $this->message->verification_count,
            'confirmations' => $this->confirmations,
            'disputes' => $this->disputes,
            'is_verified' => $this->message->is_verified,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.verification.updated';
    }
}
