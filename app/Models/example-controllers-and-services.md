# Laravel Backend Code Examples - قطر فين

## 1. Model-Controller-Policy (MCP) Structure Examples

### A. Authentication Controller with Phone OTP

```php
<?php
// app/Http/Controllers/Api/V1/Auth/AuthController.php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\OtpService;
use App\Services\Auth\PhoneVerificationService;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected PhoneVerificationService $phoneService
    ) {}

    public function sendOtp(SendOtpRequest $request)
    {
        $phone = $request->validated('phone');
        
        $result = $this->otpService->sendOtp($phone);
        
        return response()->json([
            'success' => true,
            'message' => __('auth.otp_sent'),
            'expires_in' => 600, // 10 minutes
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $phone = $request->validated('phone');
        $otp = $request->validated('otp_code');
        
        $verification = $this->otpService->verifyOtp($phone, $otp);
        
        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => __('auth.invalid_otp'),
            ], 422);
        }

        $user = $this->phoneService->findOrCreateUser($phone);
        $token = $user->createToken('qatar-fein-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function socialLogin(SocialLoginRequest $request)
    {
        $provider = $request->validated('provider');
        $accessToken = $request->validated('access_token');
        
        $user = $this->socialAuthService->authenticateWithProvider($provider, $accessToken);
        $token = $user->createToken('qatar-fein-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }
}
```

### B. Train Tracking Controller with Real-time Updates

```php
<?php
// app/Http/Controllers/Api/V1/Train/TrainTrackingController.php

namespace App\Http\Controllers\Api\V1\Train;

use App\Http\Controllers\Controller;
use App\Services\Train\TrackingService;
use App\Services\Train\LocationService;
use App\Http\Resources\TrainTripResource;
use App\Http\Requests\Train\UpdateLocationRequest;
use App\Models\Train\TrainTrip;

class TrainTrackingController extends Controller
{
    public function __construct(
        protected TrackingService $trackingService,
        protected LocationService $locationService
    ) {}

    public function getCurrentTrips()
    {
        $trips = TrainTrip::with(['schedule.route.train', 'currentStation', 'nextStation'])
            ->whereDate('trip_date', today())
            ->whereIn('status', ['boarding', 'departed', 'in_transit'])
            ->get();

        return TrainTripResource::collection($trips);
    }

    public function getTripLocation(TrainTrip $trip)
    {
        $this->authorize('view', $trip);

        $location = $this->trackingService->getCurrentLocation($trip);
        $estimatedArrival = $this->trackingService->getEstimatedArrival($trip);

        return response()->json([
            'trip_id' => $trip->id,
            'current_location' => $location,
            'estimated_arrival' => $estimatedArrival,
            'delay_minutes' => $trip->delay_minutes,
            'speed_kmh' => $trip->speed_kmh,
            'passenger_count' => $trip->passenger_count,
            'last_update' => $trip->last_location_update,
        ]);
    }

    public function updateLocation(UpdateLocationRequest $request, TrainTrip $trip)
    {
        $this->authorize('updateLocation', $trip);

        $locationData = $request->validated();
        
        $result = $this->locationService->updateTrainLocation($trip, $locationData);

        // Broadcast to WebSocket channels
        $this->trackingService->broadcastLocationUpdate($trip, $result);

        return response()->json([
            'success' => true,
            'location' => $result,
        ]);
    }
}
```

### C. Passenger Assignment Controller

```php
<?php
// app/Http/Controllers/Api/V1/Passenger/AssignmentController.php

namespace App\Http\Controllers\Api\V1\Passenger;

use App\Http\Controllers\Controller;
use App\Services\Passenger\AssignmentService;
use App\Services\Gamification\RewardService;
use App\Http\Requests\Passenger\CreateAssignmentRequest;
use App\Http\Resources\PassengerAssignmentResource;
use App\Models\Train\TrainTrip;

class AssignmentController extends Controller
{
    public function __construct(
        protected AssignmentService $assignmentService,
        protected RewardService $rewardService
    ) {}

    public function store(CreateAssignmentRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        // Check if user already has active assignment
        if ($this->assignmentService->hasActiveAssignment($user)) {
            return response()->json([
                'success' => false,
                'message' => __('assignments.already_assigned'),
            ], 422);
        }

        $trip = TrainTrip::findOrFail($data['trip_id']);
        
        $assignment = $this->assignmentService->createAssignment($user, $trip, $data);

        return new PassengerAssignmentResource($assignment);
    }

    public function updateLocation(UpdateLocationRequest $request, PassengerAssignment $assignment)
    {
        $this->authorize('update', $assignment);

        $locationData = $request->validated();
        
        $result = $this->assignmentService->updatePassengerLocation($assignment, $locationData);

        return response()->json([
            'success' => true,
            'location_updated' => $result,
        ]);
    }

    public function complete(PassengerAssignment $assignment)
    {
        $this->authorize('update', $assignment);

        $result = $this->assignmentService->completeAssignment($assignment);

        // Award rewards for successful completion
        if ($result['success']) {
            $this->rewardService->processAssignmentCompletion($assignment);
        }

        return response()->json($result);
    }
}
```

## 2. Service Layer Examples

### A. OTP Service with Redis Caching

```php
<?php
// app/Services/Auth/OtpService.php

namespace App\Services\Auth;

use App\Models\Auth\OtpVerification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OtpService
{
    protected $otpLength = 6;
    protected $otpExpiry = 600; // 10 minutes
    protected $maxAttempts = 3;

    public function sendOtp(string $phone): bool
    {
        // Generate OTP
        $otp = $this->generateOtp();
        
        // Store in database
        OtpVerification::updateOrCreate(
            ['phone' => $phone],
            [
                'otp_code' => $otp,
                'expires_at' => now()->addSeconds($this->otpExpiry),
                'attempts' => 0,
                'verified_at' => null,
            ]
        );

        // Cache for quick verification
        Cache::put("otp:{$phone}", $otp, $this->otpExpiry);

        // Send SMS (integrate with your SMS provider)
        return $this->sendSms($phone, $otp);
    }

    public function verifyOtp(string $phone, string $otp): bool
    {
        $verification = OtpVerification::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->first();

        if (!$verification) {
            return false;
        }

        $verification->increment('attempts');

        if ($verification->attempts > $this->maxAttempts) {
            $verification->update(['expires_at' => now()]);
            return false;
        }

        if ($verification->otp_code === $otp) {
            $verification->update(['verified_at' => now()]);
            Cache::forget("otp:{$phone}");
            return true;
        }

        return false;
    }

    protected function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), $this->otpLength, '0', STR_PAD_LEFT);
    }

    protected function sendSms(string $phone, string $otp): bool
    {
        // Integrate with SMS provider (Twilio, AWS SNS, etc.)
        $message = __('auth.otp_message', ['otp' => $otp, 'app_name' => 'القطر فين']);
        
        // Example integration (replace with actual SMS service)
        $response = Http::post('https://api.sms-provider.com/send', [
            'to' => $phone,
            'message' => $message,
        ]);

        return $response->successful();
    }
}
```

### B. Real-time Tracking Service with Laravel Reverb

```php
<?php
// app/Services/Train/TrackingService.php

namespace App\Services\Train;

use App\Models\Train\TrainTrip;
use App\Models\Train\TrainLocationHistory;
use App\Events\TrainLocationUpdated;
use App\Events\TrainDelayAlert;
use Illuminate\Support\Facades\Cache;

class TrackingService
{
    public function getCurrentLocation(TrainTrip $trip): array
    {
        // Try cache first
        $cached = Cache::get("train_location:{$trip->id}");
        if ($cached) {
            return $cached;
        }

        $location = [
            'latitude' => $trip->current_latitude,
            'longitude' => $trip->current_longitude,
            'speed_kmh' => $trip->speed_kmh,
            'heading' => $trip->current_heading ?? 0,
            'last_update' => $trip->last_location_update,
        ];

        Cache::put("train_location:{$trip->id}", $location, 30); // 30 seconds cache
        
        return $location;
    }

    public function updateTrainLocation(TrainTrip $trip, array $locationData): array
    {
        // Update trip location
        $trip->update([
            'current_latitude' => $locationData['latitude'],
            'current_longitude' => $locationData['longitude'],
            'speed_kmh' => $locationData['speed_kmh'] ?? 0,
            'last_location_update' => now(),
        ]);

        // Store in history
        TrainLocationHistory::create([
            'trip_id' => $trip->id,
            'latitude' => $locationData['latitude'],
            'longitude' => $locationData['longitude'],
            'speed_kmh' => $locationData['speed_kmh'] ?? 0,
            'heading' => $locationData['heading'] ?? null,
            'altitude_m' => $locationData['altitude'] ?? null,
            'accuracy_m' => $locationData['accuracy'] ?? null,
            'reported_by_user_id' => $locationData['reported_by'] ?? null,
        ]);

        // Clear cache
        Cache::forget("train_location:{$trip->id}");

        // Check for delays and update status
        $this->checkForDelays($trip);

        return $this->getCurrentLocation($trip);
    }

    public function broadcastLocationUpdate(TrainTrip $trip, array $location): void
    {
        broadcast(new TrainLocationUpdated($trip, $location));
    }

    protected function checkForDelays(TrainTrip $trip): void
    {
        $expectedArrival = $trip->estimated_arrival_time;
        $currentTime = now();
        
        if ($currentTime->gt($expectedArrival)) {
            $delayMinutes = $currentTime->diffInMinutes($expectedArrival);
            
            if ($delayMinutes > $trip->delay_minutes) {
                $trip->update(['delay_minutes' => $delayMinutes]);
                
                // Broadcast delay alert
                broadcast(new TrainDelayAlert($trip, $delayMinutes));
            }
        }
    }
}
```

## 3. Laravel Reverb WebSocket Events

### A. Train Location Event

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
        public array $location
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("trains.{$this->trip->id}.location"),
            new Channel("trains.public.updates"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'train_number' => $this->trip->schedule->route->train->number,
            'location' => $this->location,
            'status' => $this->trip->status,
            'delay_minutes' => $this->trip->delay_minutes,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }
}
```

### B. Community Message Event

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
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'user' => $this->message->user ? [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
            ] : ['name' => 'Guest'],
            'station' => [
                'id' => $this->message->station->id,
                'name' => $this->message->station->name,
            ],
            'time_passed_minutes' => $this->message->time_passed_minutes,
            'message_type' => $this->message->message_type,
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

## 4. Policy Examples

### A. Train Trip Policy

```php
<?php
// app/Policies/TrainTripPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\Train\TrainTrip;

class TrainTripPolicy
{
    public function view(?User $user, TrainTrip $trip): bool
    {
        // Public trips can be viewed by anyone
        return true;
    }

    public function updateLocation(User $user, TrainTrip $trip): bool
    {
        // Only passengers on the trip can update location
        return $trip->assignments()
            ->where('user_id', $user->id)
            ->where('status', 'boarded')
            ->exists();
    }

    public function joinCommunity(?User $user, TrainTrip $trip): bool
    {
        // Guests can view, authenticated users can participate
        return $user ? $this->isPassengerOnTrip($user, $trip) : true;
    }

    public function postMessage(User $user, TrainTrip $trip): bool
    {
        // Only authenticated passengers can post messages
        return $this->isPassengerOnTrip($user, $trip);
    }

    protected function isPassengerOnTrip(User $user, TrainTrip $trip): bool
    {
        return $trip->assignments()
            ->where('user_id', $user->id)
            ->whereIn('status', ['assigned', 'boarded', 'in_transit'])
            ->exists();
    }
}
```

## 5. Request Validation Examples

### A. Phone OTP Requests

```php
<?php
// app/Http/Requests/Auth/SendOtpRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{1,14}$/', // International format
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => __('validation.phone.required'),
            'phone.regex' => __('validation.phone.format'),
        ];
    }
}

// app/Http/Requests/Auth/VerifyOtpRequest.php
class VerifyOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{1,14}$/',
            ],
            'otp_code' => [
                'required',
                'string',
                'size:6',
                'regex:/^\d{6}$/',
            ],
        ];
    }
}
```

This comprehensive backend structure provides:

1. **MCP Architecture**: Models, Controllers, Policies separation
2. **Bilingual Support**: Spatie Translatable integration
3. **Real-time Features**: Laravel Reverb WebSocket events
4. **Authentication**: Phone OTP + Social login support
5. **Gamification**: Reward and badge systems
6. **Community Features**: Structured messaging system
7. **Security**: Proper authorization and validation
8. **Performance**: Caching and optimized queries