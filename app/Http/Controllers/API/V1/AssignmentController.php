<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PassengerAssignment;
use App\Models\Train\TrainTrip;
use App\Models\Train\Station;
use App\Models\Reward;
use App\Services\RewardService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    use ApiResponse;

    protected $rewardService;

    public function __construct(RewardService $rewardService)
    {
        $this->rewardService = $rewardService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PassengerAssignment::with(['trip.train', 'boardingStation', 'destinationStation'])
            ->where('user_id', $user->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $assignments = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->apiResponse([
            'assignments' => $assignments->items(),
            'pagination' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|exists:train_trips,id',
            'boarding_station_id' => 'required|exists:stations,id',
            'destination_station_id' => 'required|exists:stations,id|different:boarding_station_id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid input', 422, null, ['errors' => $validator->errors()]);
        }

        $user = $request->user();

        // Check if user already has an active assignment for this trip
        $existingAssignment = PassengerAssignment::where('user_id', $user->id)
            ->where('trip_id', $request->trip_id)
            ->where('status', 'active')
            ->first();

        if ($existingAssignment) {
            return $this->errorResponse('You already have an active assignment for this trip', 400);
        }

        // Verify trip is valid and active
        $trip = TrainTrip::with('train.stops')->find($request->trip_id);

        if (!$trip || $trip->status !== 'active') {
            return $this->errorResponse('Trip is not available for assignment', 400);
        }

        // Verify stations are on the train's route (from stops)
        $trainStationIds = $trip->train->stops->pluck('station_id');

        if (!$trainStationIds->contains($request->boarding_station_id) ||
            !$trainStationIds->contains($request->destination_station_id)) {
            return $this->errorResponse('Selected stations are not on the train route', 400);
        }

        // Create assignment
        $assignment = PassengerAssignment::create([
            'user_id' => $user->id,
            'trip_id' => $request->trip_id,
            'boarding_station_id' => $request->boarding_station_id,
            'destination_station_id' => $request->destination_station_id,
            'status' => 'active',
            'location_sharing_enabled' => false, // User can enable later
        ]);

        // Load relationships
        $assignment->load(['trip.train', 'boardingStation', 'destinationStation']);

        return $this->apiResponse(['assignment' => $assignment], 'Assignment created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::with(['trip.train', 'boardingStation', 'destinationStation'])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        return $this->apiResponse(['assignment' => $assignment]);
    }

    public function updateLocation(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,359',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid location data', 422, null, ['errors' => $validator->errors()]);
        }

        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Active assignment not found', 404);
        }

        if (!$assignment->location_sharing_enabled) {
            return $this->errorResponse('Location sharing is not enabled for this assignment', 400);
        }

        // Update location
        $assignment->update([
            'current_latitude' => $request->latitude,
            'current_longitude' => $request->longitude,
            'location_accuracy' => $request->accuracy,
            'speed_kmh' => $request->speed,
            'heading' => $request->heading,
            'last_location_update' => now(),
        ]);

        // Broadcast location update for real-time tracking
        broadcast(new \App\Events\PassengerLocationUpdated($assignment))->toOthers();

        return $this->apiResponse([
            'assignment_id' => $assignment->id,
            'location_updated_at' => $assignment->last_location_update,
        ], 'Location updated successfully');
    }

    public function enableLocationSharing(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Active assignment not found', 404);
        }

        $assignment->update(['location_sharing_enabled' => true]);

        return $this->apiResponse(null, 'Location sharing enabled successfully');
    }

    public function disableLocationSharing(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Active assignment not found', 404);
        }

        $assignment->update([
            'location_sharing_enabled' => false,
            'current_latitude' => null,
            'current_longitude' => null,
        ]);

        return $this->apiResponse(null, 'Location sharing disabled successfully');
    }

    public function complete(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Active assignment not found', 404);
        }

        // Mark as completed
        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'location_sharing_enabled' => false,
        ]);

        // Calculate and award rewards
        $rewards = $this->rewardService->processAssignmentCompletion($assignment);

        return $this->apiResponse([
            'assignment' => $assignment,
            'rewards' => $rewards,
        ], 'Assignment completed successfully');
    }

    public function cancel(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Active assignment not found', 404);
        }

        $assignment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'location_sharing_enabled' => false,
            'current_latitude' => null,
            'current_longitude' => null,
        ]);

        return $this->apiResponse(null, 'Assignment cancelled successfully');
    }

    public function activeAssignment(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignment = PassengerAssignment::with(['trip.train', 'boardingStation', 'destinationStation'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->apiResponse(['assignment' => null]);
        }

        return $this->apiResponse(['assignment' => $assignment]);
    }

    /**
     * Get trip progress for an assignment
     */
    public function tripProgress(int $id, Request $request): JsonResponse
    {
        $assignment = PassengerAssignment::with([
            'trip.train.stops.station',
            'boardingStation',
            'destinationStation'
        ])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        $trip = $assignment->trip;
        $train = $trip->train;

        // Get all stops for this train
        $allStops = $train->stops->sortBy('stop_number');

        // Find boarding and destination stops
        $boardingStop = $allStops->firstWhere('station_id', $assignment->boarding_station_id);
        $destinationStop = $allStops->firstWhere('station_id', $assignment->destination_station_id);

        if (!$boardingStop || !$destinationStop) {
            return $this->errorResponse('Invalid assignment stations', 400);
        }

        // Get stops between boarding and destination
        $relevantStops = $allStops->filter(function ($stop) use ($boardingStop, $destinationStop) {
            return $stop->stop_number >= $boardingStop->stop_number &&
                   $stop->stop_number <= $destinationStop->stop_number;
        });

        // Calculate progress
        $totalStops = $relevantStops->count();
        $currentStationId = $trip->current_station_id;

        $passedStops = 0;
        if ($currentStationId) {
            $currentStop = $allStops->firstWhere('station_id', $currentStationId);
            if ($currentStop) {
                $passedStops = $relevantStops->filter(function ($stop) use ($currentStop) {
                    return $stop->stop_number < $currentStop->stop_number;
                })->count();
            }
        }

        $progressPercentage = $totalStops > 0 ? round(($passedStops / $totalStops) * 100, 1) : 0;

        // Calculate estimated time remaining
        $currentTime = now();
        $estimatedArrival = null;
        $timeRemaining = null;

        if ($destinationStop->arrival_time) {
            $arrivalDateTime = Carbon::parse($trip->trip_date->format('Y-m-d') . ' ' . $destinationStop->arrival_time);

            // Add delay
            if ($trip->delay_minutes > 0) {
                $arrivalDateTime->addMinutes($trip->delay_minutes);
            }

            $estimatedArrival = $arrivalDateTime;
            $timeRemaining = $currentTime->lt($arrivalDateTime) ? $currentTime->diffInMinutes($arrivalDateTime) : 0;
        }

        return $this->apiResponse([
            'assignment_id' => $assignment->id,
            'trip_id' => $trip->id,
            'progress' => [
                'percentage' => $progressPercentage,
                'stops_passed' => $passedStops,
                'total_stops' => $totalStops,
                'stops_remaining' => $totalStops - $passedStops,
            ],
            'timing' => [
                'estimated_arrival' => $estimatedArrival?->toISOString(),
                'time_remaining_minutes' => $timeRemaining,
                'delay_minutes' => $trip->delay_minutes,
                'status' => $trip->status,
            ],
            'current_location' => [
                'station_id' => $currentStationId,
                'station' => $currentStationId ? Station::find($currentStationId) : null,
                'next_station_id' => $trip->next_station_id,
                'next_station' => $trip->next_station_id ? Station::find($trip->next_station_id) : null,
            ],
            'route' => $relevantStops->map(function ($stop) use ($currentStationId) {
                return [
                    'stop_number' => $stop->stop_number,
                    'station' => $stop->station,
                    'arrival_time' => $stop->arrival_time,
                    'departure_time' => $stop->departure_time,
                    'is_current' => $stop->station_id === $currentStationId,
                    'is_passed' => $currentStationId && $stop->stop_number < $this->getStopNumber($currentStationId, $allStops),
                ];
            })->values(),
        ]);
    }

    /**
     * Helper to get stop number for a station
     */
    private function getStopNumber($stationId, $stops)
    {
        $stop = $stops->firstWhere('station_id', $stationId);
        return $stop ? $stop->stop_number : 0;
    }
}