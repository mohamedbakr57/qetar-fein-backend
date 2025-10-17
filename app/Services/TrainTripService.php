<?php

namespace App\Services;

use App\Models\Train\Train;
use App\Models\Train\TrainTrip;
use Carbon\Carbon;

class TrainTripService
{
    /**
     * Get or create a train trip for a specific date
     */
    public function getOrCreateTrip(int $trainId, string $date): TrainTrip
    {
        $tripDate = Carbon::parse($date);

        // Check if trip already exists
        $trip = TrainTrip::where('train_id', $trainId)
            ->whereDate('trip_date', $tripDate)
            ->first();

        if ($trip) {
            return $trip;
        }

        // Create new trip
        return $this->createTrip($trainId, $tripDate);
    }

    /**
     * Create a new train trip
     */
    public function createTrip(int $trainId, Carbon $tripDate): TrainTrip
    {
        $train = Train::with('stops')->findOrFail($trainId);

        // Get first and last stop for estimated times
        $firstStop = $train->stops->sortBy('stop_number')->first();
        $lastStop = $train->stops->sortByDesc('stop_number')->first();

        if (!$firstStop || !$lastStop) {
            throw new \Exception("Train must have stops to create a trip");
        }

        // Calculate estimated departure/arrival times for the trip date
        $estimatedDeparture = Carbon::parse($tripDate->format('Y-m-d') . ' ' . $firstStop->departure_time);
        $estimatedArrival = Carbon::parse($tripDate->format('Y-m-d') . ' ' . $lastStop->arrival_time);

        // If arrival time is earlier than departure, it's next day
        if ($estimatedArrival->lt($estimatedDeparture)) {
            $estimatedArrival->addDay();
        }

        return TrainTrip::create([
            'train_id' => $trainId,
            'trip_date' => $tripDate,
            'estimated_departure_time' => $estimatedDeparture,
            'estimated_arrival_time' => $estimatedArrival,
            'status' => 'scheduled',
            'delay_minutes' => 0,
            'passenger_count' => 0,
        ]);
    }

    /**
     * Create trips for all active trains for a given date
     */
    public function createTripsForDate(string $date): int
    {
        $tripDate = Carbon::parse($date);
        $trains = Train::active()->get();
        $count = 0;

        foreach ($trains as $train) {
            try {
                $this->getOrCreateTrip($train->id, $tripDate);
                $count++;
            } catch (\Exception $e) {
                \Log::error("Failed to create trip for train {$train->id}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Update trip location (from real-time tracking)
     */
    public function updateTripLocation(
        int $tripId,
        float $latitude,
        float $longitude,
        ?float $speed = null,
        ?int $currentStationId = null,
        ?int $nextStationId = null
    ): TrainTrip {
        $trip = TrainTrip::with('train')->findOrFail($tripId);

        $trip->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'speed_kmh' => $speed,
            'current_station_id' => $currentStationId,
            'next_station_id' => $nextStationId,
            'last_location_update' => now(),
        ]);

        $trip->refresh();

        // Broadcast location update
        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed_kmh' => $speed,
            'heading' => null,
            'accuracy' => null,
        ];
        broadcast(new \App\Events\TrainLocationUpdated($trip->train, $location, $trip->delay_minutes));

        return $trip;
    }

    /**
     * Start a trip (when train departs)
     */
    public function startTrip(int $tripId): TrainTrip
    {
        $trip = TrainTrip::with('train')->findOrFail($tripId);
        $previousStatus = $trip->status;

        $trip->update([
            'status' => 'departed',
            'actual_departure_time' => now(),
        ]);

        $trip->refresh();

        // Broadcast status change
        broadcast(new \App\Events\TripStatusUpdated($trip, $previousStatus, 'departed'));

        return $trip;
    }

    /**
     * Complete a trip (when train arrives)
     */
    public function completeTrip(int $tripId): TrainTrip
    {
        $trip = TrainTrip::with('train')->findOrFail($tripId);
        $previousStatus = $trip->status;
        $previousDelay = $trip->delay_minutes;

        $trip->update([
            'status' => 'arrived',
            'actual_arrival_time' => now(),
        ]);

        // Calculate delay
        if ($trip->actual_arrival_time && $trip->estimated_arrival_time) {
            $delayMinutes = $trip->actual_arrival_time->diffInMinutes($trip->estimated_arrival_time, false);
            $trip->update(['delay_minutes' => max(0, $delayMinutes)]);
        }

        $trip->refresh();

        // Broadcast status change
        broadcast(new \App\Events\TripStatusUpdated($trip, $previousStatus, 'arrived'));

        // Broadcast delay update if changed
        if ($trip->delay_minutes !== $previousDelay) {
            broadcast(new \App\Events\TrainDelayUpdated($trip, $previousDelay, $trip->delay_minutes));
        }

        return $trip;
    }

    /**
     * Update trip delay and broadcast
     */
    public function updateTripDelay(int $tripId, int $delayMinutes): TrainTrip
    {
        $trip = TrainTrip::with('train')->findOrFail($tripId);
        $previousDelay = $trip->delay_minutes;

        // Update delay
        $trip->update(['delay_minutes' => $delayMinutes]);
        $trip->refresh();

        // Update status based on delay
        if ($delayMinutes > 0 && $trip->status === 'departed') {
            $previousStatus = $trip->status;
            $trip->update(['status' => 'delayed']);
            broadcast(new \App\Events\TripStatusUpdated($trip, $previousStatus, 'delayed'));
        }

        // Broadcast delay update
        if ($delayMinutes !== $previousDelay) {
            broadcast(new \App\Events\TrainDelayUpdated($trip, $previousDelay, $delayMinutes));
        }

        return $trip;
    }

    /**
     * Update trip status and broadcast
     */
    public function updateTripStatus(int $tripId, string $newStatus): TrainTrip
    {
        $trip = TrainTrip::with('train')->findOrFail($tripId);
        $previousStatus = $trip->status;

        $trip->update(['status' => $newStatus]);
        $trip->refresh();

        // Broadcast status update
        if ($newStatus !== $previousStatus) {
            broadcast(new \App\Events\TripStatusUpdated($trip, $previousStatus, $newStatus));
        }

        return $trip;
    }

    /**
     * Get active trips for today
     */
    public function getTodaysActiveTrips()
    {
        return TrainTrip::whereDate('trip_date', today())
            ->whereIn('status', ['scheduled', 'boarding', 'departed', 'in_transit'])
            ->with(['train', 'currentStation', 'nextStation'])
            ->get();
    }
}
