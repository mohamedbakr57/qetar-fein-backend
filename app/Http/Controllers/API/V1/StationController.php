<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Train\Station;
use App\Models\Train\Stop;
use App\Models\Train\TrainTrip;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class StationController extends Controller
{
    use ApiResponse;
    public function index(Request $request): JsonResponse
    {
        $query = Station::query();

        // Filter by city
        if ($request->has('city')) {
            $city = $request->city;
            $query->where(function($q) use ($city) {
                $q->whereJsonContains('city->ar', $city)
                  ->orWhereJsonContains('city->en', $city);
            });
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereJsonContains('name->ar', $search)
                  ->orWhereJsonContains('name->en', $search);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Location-based search (nearby stations)
        if ($request->has('latitude') && $request->has('longitude')) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radius = $request->get('radius', 50); // Default 50km radius

            $query->selectRaw("*,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
                ->having('distance', '<', $radius)
                ->orderBy('distance');
        } else {
            $query->orderBy('code');
        }

        $stations = $query->paginate(20);

        return $this->apiResponse([
            'stations' => $stations->items(),
            'pagination' => [
                'current_page' => $stations->currentPage(),
                'last_page' => $stations->lastPage(),
                'per_page' => $stations->perPage(),
                'total' => $stations->total(),
            ]
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $station = Station::find($id);

        if (!$station) {
            return $this->errorResponse('Station not found', 404);
        }

        // Get some statistics about this station
        $stats = [
            'total_trains' => Stop::where('station_id', $id)->distinct('train_id')->count(),
            'departures_count' => Stop::where('station_id', $id)->where('stop_number', 1)->count(),
            'major_stop_count' => Stop::where('station_id', $id)->where('is_major_stop', true)->count(),
        ];

        return $this->apiResponse([
            'station' => $station,
            'statistics' => $stats
        ]);
    }

    public function departures(int $id, Request $request): JsonResponse
    {
        $station = Station::find($id);

        if (!$station) {
            return $this->errorResponse('Station not found', 404);
        }

        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $currentTime = $request->get('time', Carbon::now()->format('H:i:s'));

        // Get trains that depart from this station (stop_number = 1)
        $departureStops = Stop::with(['train', 'station'])
            ->where('station_id', $id)
            ->where('stop_number', 1) // First stop = departure station
            ->whereNotNull('departure_time')
            ->where('departure_time', '>=', $currentTime) // Only future departures
            ->orderBy('departure_time')
            ->limit(20)
            ->get();

        // Get active trips for today if requested date is today
        $activeTrips = [];
        if ($date === Carbon::today()->format('Y-m-d')) {
            $trainIds = $departureStops->pluck('train_id');
            $activeTrips = TrainTrip::whereIn('train_id', $trainIds)
                ->where('trip_date', $date)
                ->get()
                ->keyBy('train_id');
        }

        $departures = $departureStops->map(function($stop) use ($activeTrips, $id) {
            $trip = $activeTrips->get($stop->train_id);

            // Get destination (last stop for this train)
            $destinationStop = Stop::with('station')
                ->where('train_id', $stop->train_id)
                ->orderByDesc('stop_number')
                ->first();

            return [
                'train' => [
                    'id' => $stop->train->id,
                    'number' => $stop->train->number,
                    'name' => $this->getStationName($stop->train->name),
                    'type' => $stop->train->type
                ],
                'departure_time' => $stop->departure_time,
                'platform' => $stop->platform,
                'destination' => $destinationStop ? [
                    'id' => $destinationStop->station->id,
                    'name' => $this->getStationName($destinationStop->station),
                    'arrival_time' => $destinationStop->arrival_time
                ] : null,
                'is_major_stop' => $stop->is_major_stop,
                'notes' => $stop->notes,
                'trip' => $trip ? [
                    'id' => $trip->id,
                    'status' => $trip->status,
                    'actual_departure_time' => $trip->actual_departure_time,
                    'delay_minutes' => $trip->delay_minutes,
                    'passenger_count' => $trip->passenger_count,
                ] : null
            ];
        });

        return $this->apiResponse([
            'station' => [
                'id' => $station->id,
                'name' => $this->getStationName($station),
                'code' => $station->code
            ],
            'date' => $date,
            'departures' => $departures
        ]);
    }

    public function arrivals(int $id, Request $request): JsonResponse
    {
        $station = Station::find($id);

        if (!$station) {
            return $this->errorResponse('Station not found', 404);
        }

        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $currentTime = $request->get('time', Carbon::now()->format('H:i:s'));

        // Get trains that arrive at this station (stop_number > 1)
        $arrivalStops = Stop::with(['train', 'station'])
            ->where('station_id', $id)
            ->where('stop_number', '>', 1) // Not the first stop
            ->whereNotNull('arrival_time')
            ->where('arrival_time', '>=', $currentTime) // Only future arrivals
            ->orderBy('arrival_time')
            ->limit(20)
            ->get();

        // Get active trips for today if requested date is today
        $activeTrips = [];
        if ($date === Carbon::today()->format('Y-m-d')) {
            $trainIds = $arrivalStops->pluck('train_id');
            $activeTrips = TrainTrip::whereIn('train_id', $trainIds)
                ->where('trip_date', $date)
                ->get()
                ->keyBy('train_id');
        }

        $arrivals = $arrivalStops->map(function($stop) use ($activeTrips) {
            $trip = $activeTrips->get($stop->train_id);

            // Get origin (first stop for this train)
            $originStop = Stop::with('station')
                ->where('train_id', $stop->train_id)
                ->where('stop_number', 1)
                ->first();

            return [
                'train' => [
                    'id' => $stop->train->id,
                    'number' => $stop->train->number,
                    'name' => $this->getStationName($stop->train->name),
                    'type' => $stop->train->type
                ],
                'arrival_time' => $stop->arrival_time,
                'departure_time' => $stop->departure_time, // In case train continues
                'platform' => $stop->platform,
                'origin' => $originStop ? [
                    'id' => $originStop->station->id,
                    'name' => $this->getStationName($originStop->station),
                    'departure_time' => $originStop->departure_time
                ] : null,
                'stop_number' => $stop->stop_number,
                'is_major_stop' => $stop->is_major_stop,
                'stop_duration_minutes' => $stop->stop_duration_minutes,
                'notes' => $stop->notes,
                'trip' => $trip ? [
                    'id' => $trip->id,
                    'status' => $trip->status,
                    'delay_minutes' => $trip->delay_minutes,
                    'current_station_id' => $trip->current_station_id,
                ] : null
            ];
        });

        return $this->apiResponse([
            'station' => [
                'id' => $station->id,
                'name' => $this->getStationName($station),
                'code' => $station->code
            ],
            'date' => $date,
            'arrivals' => $arrivals
        ]);
    }

    /**
     * Helper method to get station/train name based on available format
     */
    private function getStationName($entity): string
    {
        if (!$entity) return 'Unknown';

        $name = $entity->name ?? $entity;

        // Try to decode as JSON first
        $decoded = json_decode($name, true);
        if ($decoded && is_array($decoded)) {
            return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $decoded['ar'] ?? $name;
        }

        // If not JSON, return the raw name
        return $name;
    }
}