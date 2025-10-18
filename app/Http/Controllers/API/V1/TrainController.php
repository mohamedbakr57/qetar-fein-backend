<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Train\Train;
use App\Models\Train\Station;
use App\Models\Train\TrainTrip;
use App\Models\Train\Stop;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TrainController extends Controller
{
    use ApiResponse;
    public function index(Request $request): JsonResponse
    {
        $query = Train::query();

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

        $trains = $query->with(['trainType', 'stops.station'])->orderBy('number')->paginate(20);

        // Format trains with journey information
        $formattedTrains = collect($trains->items())->map(function($train) {
            $trainData = $train->toArray();

            // Add journey information
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

            $trainData['journey'] = $journey;
            return $trainData;
        });

        return $this->apiResponse([
            'trains' => $formattedTrains,
            'pagination' => [
                'current_page' => $trains->currentPage(),
                'last_page' => $trains->lastPage(),
                'per_page' => $trains->perPage(),
                'total' => $trains->total(),
            ]
        ], 'success', 200);
    }

    public function show(int $id): JsonResponse
    {
        $train = Train::with(['stops.station'])->find($id);
        if (!$train) {
            return $this->errorResponse('Train not found', 404);
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
        return $this->apiResponse([
            'train' => $train,
            'journey' => $journey
        ], 'success', 200);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'departure_station_id' => 'required|integer|exists:stations,id',
            'arrival_station_id' => 'required|integer|exists:stations,id',
            'train_type_id' => 'nullable|integer|exists:train_types,id'
        ]);

        $departureStationId = $request->departure_station_id;
        $arrivalStationId = $request->arrival_station_id;
        $trainTypeId = $request->train_type_id;

        // Find trains that have both stations in their route
        $query = Train::whereHas('stops', function($q) use ($departureStationId) {
            $q->where('station_id', $departureStationId);
        })->whereHas('stops', function($q) use ($arrivalStationId) {
            $q->where('station_id', $arrivalStationId);
        });

        // Apply train type filter if provided
        if ($trainTypeId) {
            $query->where('train_type_id', $trainTypeId);
        }

        $trains = $query->with(['stops' => function($q) {
            $q->orderBy('stop_number');
        }, 'stops.station', 'trainType'])->get();

        // Filter trains where departure comes before arrival and format results
        $availableTrains = [];
        foreach ($trains as $train) {
            $departureStop = $train->stops->firstWhere('station_id', $departureStationId);
            $arrivalStop = $train->stops->firstWhere('station_id', $arrivalStationId);

            // Check if departure stop comes before arrival stop
            if ($departureStop && $arrivalStop && $departureStop->stop_number < $arrivalStop->stop_number) {
                // Calculate stops between departure and arrival
                $stopsBetween = $train->stops->filter(function($stop) use ($departureStop, $arrivalStop) {
                    return $stop->stop_number > $departureStop->stop_number &&
                           $stop->stop_number < $arrivalStop->stop_number;
                })->count();

                // Calculate trip duration
                $tripDuration = $this->calculateTripDuration(
                    $departureStop->departure_time,
                    $arrivalStop->arrival_time
                );

                $availableTrains[] = [
                    'train_id' => $train->id,
                    'train_number' => $train->number,
                    'train_name' => $train->name,
                    'train_type' => $train->trainType ? [
                        'id' => $train->trainType->id,
                        'name' => $train->trainType->name,
                        'description' => $train->trainType->description ?? null
                    ] : null,
                    'departure' => [
                        'station_id' => $departureStop->station_id,
                        'station_name' => $this->getStationName($departureStop->station),
                        'time' => $departureStop->departure_time,
                        'platform' => $departureStop->platform
                    ],
                    'arrival' => [
                        'station_id' => $arrivalStop->station_id,
                        'station_name' => $this->getStationName($arrivalStop->station),
                        'time' => $arrivalStop->arrival_time,
                        'platform' => $arrivalStop->platform
                    ],
                    'trip_duration' => $tripDuration,
                    'stops_between' => $stopsBetween,
                    'total_stops' => $stopsBetween + 2,
                    'amenities' => $train->amenities,
                    'capacity' => $train->capacity
                ];
            }
        }

        // Sort by departure time
        usort($availableTrains, function($a, $b) {
            return strcmp($a['departure']['time'], $b['departure']['time']);
        });

        return $this->apiResponse([
            'available_trains' => $availableTrains,
            'count' => count($availableTrains),
            'search_criteria' => [
                'departure_station_id' => $departureStationId,
                'arrival_station_id' => $arrivalStationId,
                'train_type_id' => $trainTypeId
            ]
        ], 'success', 200);
    }

    public function schedule(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'departure_station_id' => 'nullable|integer|exists:stations,id',
            'arrival_station_id' => 'nullable|integer|exists:stations,id'
        ]);

        $train = Train::with(['stops' => function($q) {
            $q->orderBy('stop_number');
        }, 'stops.station', 'trainType'])->find($id);

        if (!$train) {
            return $this->errorResponse('Train not found', 404);
        }

        $departureStationId = $request->departure_station_id;
        $arrivalStationId = $request->arrival_station_id;

        // If both stations provided, show only the segment
        if ($departureStationId && $arrivalStationId) {
            $departureStop = $train->stops->firstWhere('station_id', $departureStationId);
            $arrivalStop = $train->stops->firstWhere('station_id', $arrivalStationId);

            if (!$departureStop || !$arrivalStop || $departureStop->stop_number >= $arrivalStop->stop_number) {
                return $this->errorResponse('Invalid station combination for this train', 400);
            }

            // Filter stops to show only the journey segment
            $schedule = $train->stops->filter(function($stop) use ($departureStop, $arrivalStop) {
                return $stop->stop_number >= $departureStop->stop_number &&
                       $stop->stop_number <= $arrivalStop->stop_number;
            });
        } else {
            // Show complete journey
            $schedule = $train->stops;
        }

        // Format schedule
        $formattedSchedule = $schedule->map(function($stop) {
            return [
                'stop_number' => $stop->stop_number,
                'station_id' => $stop->station->id,
                'station_name' => $this->getStationName($stop->station),
                'station_code' => $stop->station->code,
                'arrival_time' => $stop->arrival_time,
                'departure_time' => $stop->departure_time,
                'platform' => $stop->platform,
                'stop_duration_minutes' => $stop->stop_duration_minutes,
                'is_major_stop' => $stop->is_major_stop,
                'notes' => $stop->notes
            ];
        })->values();

        return $this->apiResponse([
            'train' => [
                'id' => $train->id,
                'number' => $train->number,
                'name' => $train->name,
                'train_type' => $train->trainType ? [
                    'id' => $train->trainType->id,
                    'name' => $train->trainType->name,
                    'description' => $train->trainType->description ?? null
                ] : null,
                'status' => $train->status,
                'operator' => $train->operator,
                'amenities' => $train->amenities,
                'capacity' => $train->capacity
            ],
            'schedule' => $formattedSchedule,
            'journey_summary' => [
                'origin' => [
                    'station_name' => $this->getStationName($formattedSchedule->first()['station_id'] ? Station::find($formattedSchedule->first()['station_id']) : null),
                    'departure_time' => $formattedSchedule->first()['departure_time'] ?? null
                ],
                'destination' => [
                    'station_name' => $this->getStationName($formattedSchedule->last()['station_id'] ? Station::find($formattedSchedule->last()['station_id']) : null),
                    'arrival_time' => $formattedSchedule->last()['arrival_time'] ?? null
                ],
                'total_stops' => $formattedSchedule->count(),
                'major_stops' => $formattedSchedule->where('is_major_stop', true)->count(),
                'estimated_duration' => $this->calculateJourneyDuration($formattedSchedule)
            ]
        ], 'success', 200);
    }

    public function location(int $id, Request $request): JsonResponse
    {
        $train = Train::find($id);

        if (!$train) {
            return $this->errorResponse('Train not found', 404);
        }

        $date = $request->get('date', Carbon::today()->format('Y-m-d'));

        // Get active trip for the train on the specified date
        $trip = TrainTrip::where('train_id', $id)
            ->where('trip_date', $date)
            ->whereIn('status', ['active', 'in_transit', 'departed'])
            ->first();

        if (!$trip) {
            return $this->apiResponse([
                'train' => $train,
                'location' => null
            ], 'No active trip found for this date', 200);
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

        return $this->apiResponse([
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
        ], 'success', 200);
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

        return $this->apiResponse([
            'live_trains' => $liveTrains,
            'count' => $liveTrains->count(),
            'last_updated' => now()
        ], 'success', 200);
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

    /**
     * Calculate trip duration between two times
     */
    private function calculateTripDuration(?string $departureTime, ?string $arrivalTime): ?string
    {
        if (!$departureTime || !$arrivalTime) {
            return null;
        }

        $start = Carbon::parse($departureTime);
        $end = Carbon::parse($arrivalTime);

        $diffInMinutes = $start->diffInMinutes($end);
        $hours = intval($diffInMinutes / 60);
        $minutes = $diffInMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }
}