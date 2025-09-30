<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Train\Train;
use App\Models\Train\Station;
use App\Models\Train\TrainTrip;
use App\Models\Train\Stop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TrainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Train::with(['operator']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereJsonContains('name->ar', $search)
                  ->orWhereJsonContains('name->en', $search);
            });
        }

        $trains = $query->orderBy('number')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => [
                'trains' => $trains->items(),
                'pagination' => [
                    'current_page' => $trains->currentPage(),
                    'last_page' => $trains->lastPage(),
                    'per_page' => $trains->perPage(),
                    'total' => $trains->total(),
                ]
            ]
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $train = Train::with(['stops.station'])->find($id);

        if (!$train) {
            return response()->json([
                'status' => 'error',
                'message' => 'Train not found'
            ], 404);
        }

        // Format journey information
        $journey = null;
        if ($train->stops->isNotEmpty()) {
            $firstStop = $train->stops->first();
            $lastStop = $train->stops->last();

            $journey = [
                'origin' => [
                    'station_id' => $firstStop->station->id,
                    'station_name' => $this->getStationName($firstStop->station),
                    'departure_time' => $firstStop->departure_time
                ],
                'destination' => [
                    'station_id' => $lastStop->station->id,
                    'station_name' => $this->getStationName($lastStop->station),
                    'arrival_time' => $lastStop->arrival_time
                ],
                'total_stops' => $train->stops->count(),
                'major_stops' => $train->stops->where('is_major_stop', true)->count()
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'train' => $train,
                'journey' => $journey
            ]
        ]);
    }

    public function schedule(int $id, Request $request): JsonResponse
    {
        $train = Train::with(['stops.station'])->find($id);

        if (!$train) {
            return response()->json([
                'status' => 'error',
                'message' => 'Train not found'
            ], 404);
        }

        // Format complete journey schedule
        $schedule = $train->stops->map(function($stop) {
            return [
                'stop_number' => $stop->stop_number,
                'station_id' => $stop->station->id,
                'station_name' => $this->getStationName($stop->station),
                'arrival_time' => $stop->arrival_time,
                'departure_time' => $stop->departure_time,
                'platform' => $stop->platform,
                'stop_duration_minutes' => $stop->stop_duration_minutes,
                'is_major_stop' => $stop->is_major_stop,
                'notes' => $stop->notes
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'train' => [
                    'id' => $train->id,
                    'number' => $train->number,
                    'name' => $train->name,
                    'type' => $train->type,
                    'status' => $train->status
                ],
                'schedule' => $schedule,
                'journey_summary' => [
                    'total_stops' => $schedule->count(),
                    'major_stops' => $schedule->where('is_major_stop', true)->count(),
                    'estimated_duration' => $this->calculateJourneyDuration($schedule)
                ]
            ]
        ]);
    }

    public function location(int $id, Request $request): JsonResponse
    {
        $train = Train::find($id);

        if (!$train) {
            return response()->json([
                'status' => 'error',
                'message' => 'Train not found'
            ], 404);
        }

        $date = $request->get('date', Carbon::today()->format('Y-m-d'));

        // Get active trip for the train on the specified date
        $trip = TrainTrip::where('train_id', $id)
            ->where('trip_date', $date)
            ->whereIn('status', ['active', 'in_transit', 'departed'])
            ->first();

        if (!$trip) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'train' => $train,
                    'location' => null,
                    'message' => 'No active trip found for this date'
                ]
            ]);
        }

        // Get latest location if available
        $location = null;
        $currentStation = null;

        if ($trip->current_latitude && $trip->current_longitude) {
            $location = [
                'latitude' => $trip->current_latitude,
                'longitude' => $trip->current_longitude,
                'speed_kmh' => $trip->speed_kmh,
                'heading' => $trip->heading,
                'last_updated' => $trip->updated_at
            ];
        }

        if ($trip->current_station_id) {
            $currentStation = Station::find($trip->current_station_id);
        }

        // Get train with stops
        $train = Train::with(['stops.station'])->find($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'train' => [
                    'id' => $train->id,
                    'number' => $train->number,
                    'name' => $train->name,
                    'type' => $train->type
                ],
                'trip' => [
                    'id' => $trip->id,
                    'trip_date' => $trip->trip_date,
                    'status' => $trip->status,
                    'actual_departure_time' => $trip->actual_departure_time,
                    'estimated_arrival_time' => $trip->estimated_arrival_time,
                    'delay_minutes' => $trip->delay_minutes,
                    'passenger_count' => $trip->passenger_count,
                ],
                'location' => $location,
                'current_station' => $currentStation ? [
                    'id' => $currentStation->id,
                    'name' => $this->getStationName($currentStation),
                    'code' => $currentStation->code
                ] : null,
                'journey' => $train->stops->map(function($stop) {
                    return [
                        'stop_number' => $stop->stop_number,
                        'station_name' => $this->getStationName($stop->station),
                        'arrival_time' => $stop->arrival_time,
                        'departure_time' => $stop->departure_time,
                        'is_major_stop' => $stop->is_major_stop
                    ];
                })
            ]
        ]);
    }

    public function liveTrains(Request $request): JsonResponse
    {
        $activeTrips = TrainTrip::with(['train'])
            ->whereIn('status', ['active', 'in_transit', 'departed'])
            ->where('trip_date', Carbon::today()->format('Y-m-d'))
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get();

        $liveTrains = $activeTrips->map(function($trip) {
            $currentStation = null;
            if ($trip->current_station_id) {
                $currentStation = Station::find($trip->current_station_id);
            }

            return [
                'trip_id' => $trip->id,
                'train' => [
                    'id' => $trip->train->id,
                    'number' => $trip->train->number,
                    'name' => $trip->train->name,
                    'type' => $trip->train->type
                ],
                'location' => [
                    'latitude' => $trip->current_latitude,
                    'longitude' => $trip->current_longitude,
                    'speed_kmh' => $trip->speed_kmh,
                    'heading' => $trip->heading,
                ],
                'status' => $trip->status,
                'delay_minutes' => $trip->delay_minutes,
                'current_station' => $currentStation ? [
                    'id' => $currentStation->id,
                    'name' => $this->getStationName($currentStation),
                    'code' => $currentStation->code
                ] : null,
                'passenger_count' => $trip->passenger_count,
                'last_updated' => $trip->updated_at
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'live_trains' => $liveTrains,
                'count' => $liveTrains->count(),
                'last_updated' => now()
            ]
        ]);
    }

    /**
     * Helper method to get station name based on available format
     */
    private function getStationName($station): string
    {
        if (!$station) return 'Unknown Station';

        $name = $station->name;

        // Try to decode as JSON first
        $decoded = json_decode($name, true);
        if ($decoded && is_array($decoded)) {
            return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $decoded['ar'] ?? $name;
        }

        // If not JSON, return the raw name
        return $name;
    }

    /**
     * Calculate journey duration from schedule
     */
    private function calculateJourneyDuration($schedule): ?string
    {
        if ($schedule->isEmpty()) return null;

        $firstStop = $schedule->first();
        $lastStop = $schedule->last();

        if (!$firstStop['departure_time'] || !$lastStop['arrival_time']) {
            return null;
        }

        $start = Carbon::parse($firstStop['departure_time']);
        $end = Carbon::parse($lastStop['arrival_time']);

        $diffInMinutes = $start->diffInMinutes($end);
        $hours = intval($diffInMinutes / 60);
        $minutes = $diffInMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }
}