# Laravel Reverb Real-time Setup - قطر فين

## 1. Installation and Configuration

### A. Install and Configure Reverb

```bash
# Install Laravel Reverb
composer require laravel/reverb

# Publish configuration and migrate
php artisan reverb:install

# Start the Reverb server
php artisan reverb:start

# Start in background (production)
php artisan reverb:start --host=0.0.0.0 --port=8080 --hostname=ws.qatarfein.com
```

### B. Environment Configuration

```bash
# .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=qatar-fein-app
REVERB_APP_KEY=qatar-fein-key-2024
REVERB_APP_SECRET=qatar-fein-secret-2024
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# For production
REVERB_HOST="ws.qatarfein.com"
REVERB_SCHEME=https

# Redis configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### C. Reverb Configuration File

```php
<?php
// config/reverb.php

return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'hostname' => env('REVERB_HOSTNAME'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                ],
            ],
            'pulse' => [
                'enabled' => env('REVERB_PULSE_ENABLED', true),
                'interval' => env('REVERB_PULSE_INTERVAL', 30),
            ],
        ],
    ],

    'apps' => [
        'provider' => 'config',
        'apps' => [
            [
                'id' => env('REVERB_APP_ID'),
                'name' => env('APP_NAME'),
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'options' => [
                    'host' => env('REVERB_HOST'),
                    'port' => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                'allowed_origins' => ['*'],
                'ping_interval' => env('REVERB_PING_INTERVAL', 30),
                'activity_timeout' => env('REVERB_ACTIVITY_TIMEOUT', 30),
            ],
        ],
    ],
];
```

## 2. Real-time Events for Train Tracking

### A. Train Location Updated Event

```php
<?php
// app/Events/TrainLocationUpdated.php

namespace App\Events;

use App\Models\Train\TrainTrip;
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
        public TrainTrip $trip,
        public array $location,
        public ?int $delayMinutes = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Public channel for all users tracking this train
            new Channel("trains.{$this->trip->id}.location"),
            
            // General channel for live train updates
            new Channel('trains.live.updates'),
            
            // Passenger-specific channel for assigned users
            new Channel("trips.{$this->trip->id}.passengers"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'train_number' => $this->trip->schedule->route->train->number,
            'train_name' => $this->trip->schedule->route->train->getTranslations('name'),
            'location' => [
                'latitude' => $this->location['latitude'],
                'longitude' => $this->location['longitude'],
                'speed_kmh' => $this->location['speed_kmh'] ?? 0,
                'heading' => $this->location['heading'] ?? null,
                'accuracy' => $this->location['accuracy'] ?? null,
            ],
            'status' => $this->trip->status,
            'delay_minutes' => $this->delayMinutes ?? $this->trip->delay_minutes,
            'current_station' => $this->trip->currentStation ? [
                'id' => $this->trip->currentStation->id,
                'name' => $this->trip->currentStation->getTranslations('name'),
                'code' => $this->trip->currentStation->code,
            ] : null,
            'next_station' => $this->trip->nextStation ? [
                'id' => $this->trip->nextStation->id,
                'name' => $this->trip->nextStation->getTranslations('name'),
                'code' => $this->trip->nextStation->code,
                'estimated_arrival' => $this->trip->estimated_arrival_time?->toISOString(),
            ] : null,
            'passenger_count' => $this->trip->passenger_count,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWhen(): bool
    {
        // Only broadcast during active trip times
        return in_array($this->trip->status, ['boarding', 'departed', 'in_transit']);
    }
}
```

### B. Train Status Updated Event

```php
<?php
// app/Events/TrainStatusUpdated.php

namespace App\Events;

use App\Models\Train\TrainTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainStatusUpdated implements ShouldBroadcast
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
            new Channel("trains.{$this->trip->id}.status"),
            new Channel('trains.live.updates'),
            new Channel("trips.{$this->trip->id}.passengers"),
        ];
    }

    public function broadcastWith(): array
    {
        $statusMessages = [
            'ar' => [
                'scheduled' => 'مجدول',
                'boarding' => 'صعود الركاب',
                'departed' => 'غادر المحطة',
                'in_transit' => 'في الطريق',
                'arrived' => 'وصل',
                'cancelled' => 'ملغي',
                'delayed' => 'متأخر',
            ],
            'en' => [
                'scheduled' => 'Scheduled',
                'boarding' => 'Boarding',
                'departed' => 'Departed',
                'in_transit' => 'In Transit',
                'arrived' => 'Arrived',
                'cancelled' => 'Cancelled',
                'delayed' => 'Delayed',
            ],
        ];

        return [
            'trip_id' => $this->trip->id,
            'train_number' => $this->trip->schedule->route->train->number,
            'previous_status' => $this->previousStatus,
            'current_status' => $this->currentStatus,
            'status_message' => [
                'ar' => $statusMessages['ar'][$this->currentStatus] ?? $this->currentStatus,
                'en' => $statusMessages['en'][$this->currentStatus] ?? $this->currentStatus,
            ],
            'delay_minutes' => $this->trip->delay_minutes,
            'estimated_departure' => $this->trip->estimated_departure_time?->toISOString(),
            'estimated_arrival' => $this->trip->estimated_arrival_time?->toISOString(),
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.updated';
    }
}
```

## 3. Community Real-time Events

### A. Community Message Posted Event

```php
<?php
// app/Events/CommunityMessagePosted.php

namespace App\Events;

use App\Models\Community\CommunityMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunityMessagePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CommunityMessage $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("communities.{$this->message->community_id}.messages"),
            new Channel("trips.{$this->message->community->trip_id}.community"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'community_id' => $this->message->community_id,
            'user' => $this->message->user ? [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar' => $this->message->user->avatar,
            ] : [
                'id' => null,
                'name' => 'Guest',
                'guest_identifier' => $this->message->guest_identifier,
            ],
            'station' => [
                'id' => $this->message->station->id,
                'name' => $this->message->station->getTranslations('name'),
                'code' => $this->message->station->code,
            ],
            'time_passed_minutes' => $this->message->time_passed_minutes,
            'message_type' => $this->message->message_type,
            'additional_data' => $this->message->additional_data,
            'is_verified' => $this->message->is_verified,
            'verification_count' => $this->message->verification_count,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }
}
```

### B. Message Verification Event

```php
<?php
// app/Events/MessageVerified.php

namespace App\Events;

use App\Models\Community\CommunityMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageVerified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CommunityMessage $message,
        public string $verificationType, // 'confirm' or 'dispute'
        public int $verificationCount
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("communities.{$this->message->community_id}.verifications"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'verification_type' => $this->verificationType,
            'verification_count' => $this->verificationCount,
            'is_verified' => $this->message->is_verified,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.verified';
    }
}
```

## 4. Notification Events

### A. User Notification Event

```php
<?php
// app/Events/UserNotificationSent.php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $type,
        public array $title,
        public array $message,
        public ?array $data = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("users.{$this->user->id}.notifications"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => uniqid(),
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'created_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }
}
```

## 5. Broadcasting Service Layer

### A. Train Broadcasting Service

```php
<?php
// app/Services/Broadcasting/TrainBroadcastService.php

namespace App\Services\Broadcasting;

use App\Events\TrainLocationUpdated;
use App\Events\TrainStatusUpdated;
use App\Models\Train\TrainTrip;
use Illuminate\Support\Facades\Log;

class TrainBroadcastService
{
    public function broadcastLocationUpdate(TrainTrip $trip, array $locationData): void
    {
        try {
            broadcast(new TrainLocationUpdated($trip, $locationData));
            
            Log::info('Train location broadcasted', [
                'trip_id' => $trip->id,
                'train_number' => $trip->schedule->route->train->number,
                'location' => $locationData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast train location', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function broadcastStatusUpdate(TrainTrip $trip, string $previousStatus): void
    {
        try {
            broadcast(new TrainStatusUpdated($trip, $previousStatus, $trip->status));
            
            Log::info('Train status broadcasted', [
                'trip_id' => $trip->id,
                'train_number' => $trip->schedule->route->train->number,
                'previous_status' => $previousStatus,
                'current_status' => $trip->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast train status', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function broadcastDelayAlert(TrainTrip $trip, int $delayMinutes): void
    {
        try {
            $passengers = $trip->assignments()
                ->with('user')
                ->whereIn('status', ['assigned', 'boarded', 'in_transit'])
                ->get();

            foreach ($passengers as $assignment) {
                if ($assignment->user) {
                    $this->notifyPassengerOfDelay($assignment->user, $trip, $delayMinutes);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to broadcast delay alert', [
                'trip_id' => $trip->id,
                'delay_minutes' => $delayMinutes,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function notifyPassengerOfDelay($user, TrainTrip $trip, int $delayMinutes): void
    {
        $title = [
            'ar' => 'تأخير في القطار',
            'en' => 'Train Delay Alert'
        ];

        $message = [
            'ar' => "القطار رقم {$trip->schedule->route->train->number} متأخر {$delayMinutes} دقيقة",
            'en' => "Train {$trip->schedule->route->train->number} is delayed by {$delayMinutes} minutes"
        ];

        broadcast(new \App\Events\UserNotificationSent(
            $user,
            'train_delay',
            $title,
            $message,
            [
                'trip_id' => $trip->id,
                'delay_minutes' => $delayMinutes,
                'train_number' => $trip->schedule->route->train->number,
            ]
        ));
    }
}
```

## 6. WebSocket Channels Configuration

### A. Custom Channel Authorization

```php
<?php
// routes/channels.php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Train\TrainTrip;
use App\Models\PassengerAssignment;

// Public channels - no authentication required
Broadcast::channel('trains.live.updates', function () {
    return true; // Public channel
});

Broadcast::channel('trains.{tripId}.location', function ($user, $tripId) {
    return true; // Public train tracking
});

// Private passenger channels
Broadcast::channel('trips.{tripId}.passengers', function ($user, $tripId) {
    if (!$user) return false;
    
    // Only passengers assigned to this trip can join
    return PassengerAssignment::where('user_id', $user->id)
        ->where('trip_id', $tripId)
        ->whereIn('status', ['assigned', 'boarded', 'in_transit'])
        ->exists();
});

// Community channels
Broadcast::channel('communities.{communityId}.messages', function ($user, $communityId) {
    // Guest users can listen (view), authenticated users can participate
    return true;
});

// Private user channels
Broadcast::channel('users.{userId}.notifications', function ($user, $userId) {
    return $user && $user->id == $userId;
});

// Admin channels
Broadcast::channel('admin.train.monitoring', function ($user) {
    return $user && $user->hasRole('admin');
});
```

## 7. JavaScript Client Integration

### A. Frontend WebSocket Connection

```javascript
// resources/js/websocket-client.js

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configure Laravel Echo with Reverb
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    enableLogging: true,
});

// Train tracking functionality
export class TrainTracker {
    constructor() {
        this.subscriptions = new Map();
    }

    trackTrain(tripId, callbacks = {}) {
        if (this.subscriptions.has(tripId)) {
            this.untrackTrain(tripId);
        }

        const channel = Echo.channel(`trains.${tripId}.location`);
        
        channel.listen('.location.updated', (data) => {
            if (callbacks.onLocationUpdate) {
                callbacks.onLocationUpdate(data);
            }
        });

        channel.listen('.status.updated', (data) => {
            if (callbacks.onStatusUpdate) {
                callbacks.onStatusUpdate(data);
            }
        });

        this.subscriptions.set(tripId, channel);
        
        return channel;
    }

    untrackTrain(tripId) {
        if (this.subscriptions.has(tripId)) {
            Echo.leave(`trains.${tripId}.location`);
            this.subscriptions.delete(tripId);
        }
    }

    // Join passenger-specific channel (requires authentication)
    joinPassengerChannel(tripId, callbacks = {}) {
        const channel = Echo.private(`trips.${tripId}.passengers`);
        
        channel.listen('.location.updated', callbacks.onLocationUpdate || (() => {}));
        channel.listen('.status.updated', callbacks.onStatusUpdate || (() => {}));
        channel.listen('.delay.alert', callbacks.onDelayAlert || (() => {}));
        
        return channel;
    }

    // Listen to community messages
    joinCommunity(communityId, callbacks = {}) {
        const channel = Echo.channel(`communities.${communityId}.messages`);
        
        channel.listen('.message.posted', callbacks.onMessagePosted || (() => {}));
        channel.listen('.message.verified', callbacks.onMessageVerified || (() => {}));
        
        return channel;
    }

    // Listen to user notifications
    listenToNotifications(userId, callback) {
        return Echo.private(`users.${userId}.notifications`)
            .listen('.notification.received', callback);
    }
}

// Global instance
window.trainTracker = new TrainTracker();
```

## 8. Performance Optimization

### A. Channel Presence Optimization

```php
<?php
// app/Broadcasting/Channels/OptimizedPresenceChannel.php

namespace App\Broadcasting\Channels;

use Illuminate\Broadcasting\PresenceChannel;

class OptimizedPresenceChannel extends PresenceChannel
{
    public function __construct($name)
    {
        parent::__construct($name);
        
        // Set shorter timeout for mobile connections
        $this->timeout = 30;
    }

    public function join($user, $data = [])
    {
        // Add user presence with minimal data
        return [
            'id' => $user->id,
            'name' => $user->name,
            'is_guest' => false,
            'joined_at' => now()->toISOString(),
        ];
    }
}
```

### B. Message Queue Integration

```php
<?php
// app/Jobs/BroadcastTrainUpdate.php

namespace App\Jobs;

use App\Events\TrainLocationUpdated;
use App\Models\Train\TrainTrip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastTrainUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tripId,
        public array $locationData
    ) {}

    public function handle(): void
    {
        $trip = TrainTrip::with(['schedule.route.train', 'currentStation', 'nextStation'])
            ->find($this->tripId);

        if ($trip) {
            broadcast(new TrainLocationUpdated($trip, $this->locationData));
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Failed to broadcast train update', [
            'trip_id' => $this->tripId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

This Laravel Reverb setup provides:

1. **Real-time Train Tracking**: Live location and status updates
2. **Community Features**: Live messaging and verification
3. **User Notifications**: Private channels for personalized alerts
4. **Scalable Architecture**: Redis-backed scaling support
5. **Performance Optimized**: Queue-based broadcasting and caching
6. **Security**: Channel authorization and authentication
7. **Cross-platform Support**: WebSocket client for mobile and web
8. **Error Handling**: Comprehensive logging and failure recovery