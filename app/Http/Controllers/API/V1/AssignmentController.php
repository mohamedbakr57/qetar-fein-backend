<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PassengerAssignment;
use App\Models\Train\TrainTrip;
use App\Models\Train\Station;
use App\Models\Reward;
use App\Services\RewardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AssignmentController extends Controller
{
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

        return response()->json([
            'status' => 'success',
            'data' => [
                'assignments' => $assignments->items(),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                ]
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
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if user already has an active assignment for this trip
        $existingAssignment = PassengerAssignment::where('user_id', $user->id)
            ->where('trip_id', $request->trip_id)
            ->where('status', 'active')
            ->first();

        if ($existingAssignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have an active assignment for this trip'
            ], 400);
        }

        // Verify trip is valid and active
        $trip = TrainTrip::with('train.stops')->find($request->trip_id);

        if (!$trip || $trip->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Trip is not available for assignment'
            ], 400);
        }

        // Verify stations are on the train's route (from stops)
        $trainStationIds = $trip->train->stops->pluck('station_id');

        if (!$trainStationIds->contains($request->boarding_station_id) ||
            !$trainStationIds->contains($request->destination_station_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected stations are not on the train route'
            ], 400);
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

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment created successfully',
            'data' => [
                'assignment' => $assignment
            ]
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::with(['trip.train', 'boardingStation', 'destinationStation'])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assignment not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'assignment' => $assignment
            ]
        ]);
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
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid location data',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active assignment not found'
            ], 404);
        }

        if (!$assignment->location_sharing_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location sharing is not enabled for this assignment'
            ], 400);
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

        // Broadcast location update (implement in real-time service)
        // event(new PassengerLocationUpdated($assignment));

        return response()->json([
            'status' => 'success',
            'message' => 'Location updated successfully',
            'data' => [
                'assignment_id' => $assignment->id,
                'location_updated_at' => $assignment->last_location_update,
            ]
        ]);
    }

    public function enableLocationSharing(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active assignment not found'
            ], 404);
        }

        $assignment->update(['location_sharing_enabled' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Location sharing enabled successfully'
        ]);
    }

    public function disableLocationSharing(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active assignment not found'
            ], 404);
        }

        $assignment->update([
            'location_sharing_enabled' => false,
            'current_latitude' => null,
            'current_longitude' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Location sharing disabled successfully'
        ]);
    }

    public function complete(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active assignment not found'
            ], 404);
        }

        // Mark as completed
        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'location_sharing_enabled' => false,
        ]);

        // Calculate and award rewards
        $rewards = $this->rewardService->processAssignmentCompletion($assignment);

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment completed successfully',
            'data' => [
                'assignment' => $assignment,
                'rewards' => $rewards,
            ]
        ]);
    }

    public function cancel(int $id): JsonResponse
    {
        $assignment = PassengerAssignment::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Active assignment not found'
            ], 404);
        }

        $assignment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'location_sharing_enabled' => false,
            'current_latitude' => null,
            'current_longitude' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Assignment cancelled successfully'
        ]);
    }

    public function activeAssignment(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignment = PassengerAssignment::with(['trip.train', 'boardingStation', 'destinationStation'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'assignment' => null
                ]
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'assignment' => $assignment
            ]
        ]);
    }
}