<?php

namespace App\Services;

use App\Models\Train\TrainTrip;
use App\Models\Train\Stop;
use App\Models\PassengerAssignment;
use App\Models\CommunityMessage;
use App\Events\TrainDelayUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DelayEstimationService
{
    protected TrainTripService $trainTripService;

    public function __construct(TrainTripService $trainTripService)
    {
        $this->trainTripService = $trainTripService;
    }

    /**
     * Estimate delay based on passenger GPS data and community reports
     */
    public function estimateDelay(int $tripId): array
    {
        $trip = TrainTrip::with(['train.stops'])->find($tripId);

        if (!$trip) {
            return ['error' => 'Trip not found'];
        }

        // 1. Get passenger GPS data
        $passengerLocations = $this->getPassengerLocations($tripId);

        // 2. Get community delay reports
        $communityReports = $this->getCommunityDelayReports($tripId);

        // 3. Calculate estimated position based on passenger data
        $estimatedPosition = $this->estimateTrainPosition($trip, $passengerLocations);

        // 4. Calculate expected position based on schedule
        $expectedPosition = $this->getExpectedPosition($trip);

        // 5. Calculate delay
        $delayMinutes = $this->calculateDelay($trip, $estimatedPosition, $expectedPosition, $communityReports);

        // 6. Update trip delay if significant change
        if ($delayMinutes !== null && abs($delayMinutes - $trip->delay_minutes) >= 2) {
            $this->trainTripService->updateTripDelay($tripId, $delayMinutes);
        }

        return [
            'trip_id' => $tripId,
            'current_delay_minutes' => $trip->delay_minutes,
            'estimated_delay_minutes' => $delayMinutes,
            'confidence_level' => $this->calculateConfidence($passengerLocations, $communityReports),
            'data_sources' => [
                'passenger_gps_count' => count($passengerLocations),
                'community_reports_count' => count($communityReports),
            ],
            'estimated_position' => $estimatedPosition,
            'expected_position' => $expectedPosition,
        ];
    }

    /**
     * Get active passenger locations for this trip
     */
    protected function getPassengerLocations(int $tripId): array
    {
        $assignments = PassengerAssignment::where('trip_id', $tripId)
            ->where('status', 'active')
            ->where('location_sharing_enabled', true)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->where('last_location_update', '>', now()->subMinutes(5)) // Only recent locations
            ->get();

        return $assignments->map(function ($assignment) {
            return [
                'latitude' => $assignment->current_latitude,
                'longitude' => $assignment->current_longitude,
                'speed_kmh' => $assignment->speed_kmh,
                'timestamp' => $assignment->last_location_update,
                'boarding_station_id' => $assignment->boarding_station_id,
            ];
        })->toArray();
    }

    /**
     * Get community delay reports for this trip
     */
    protected function getCommunityDelayReports(int $tripId): array
    {
        $community = \App\Models\Community::where('trip_id', $tripId)->first();

        if (!$community) {
            return [];
        }

        $messages = CommunityMessage::where('community_id', $community->id)
            ->where('message_type', 'delay_report')
            ->where('created_at', '>', now()->subHours(2)) // Recent reports only
            ->where('is_verified', true) // Only verified reports
            ->get();

        return $messages->map(function ($message) {
            $additionalData = $message->additional_data ?? [];
            return [
                'delay_minutes' => $additionalData['delay_minutes'] ?? 0,
                'station_id' => $message->station_id,
                'time_passed_minutes' => $message->time_passed_minutes,
                'timestamp' => $message->created_at,
                'verification_count' => $message->verification_count,
            ];
        })->toArray();
    }

    /**
     * Estimate train position based on passenger GPS clustering
     */
    protected function estimateTrainPosition(TrainTrip $trip, array $passengerLocations): ?array
    {
        if (empty($passengerLocations)) {
            return null;
        }

        // Calculate centroid (average position) of all passenger locations
        $totalLat = 0;
        $totalLon = 0;
        $count = count($passengerLocations);

        foreach ($passengerLocations as $location) {
            $totalLat += $location['latitude'];
            $totalLon += $location['longitude'];
        }

        $centroidLat = $totalLat / $count;
        $centroidLon = $totalLon / $count;

        // Find nearest station to centroid
        $nearestStation = $this->findNearestStation($trip, $centroidLat, $centroidLon);

        // Calculate average speed
        $avgSpeed = 0;
        $speedCount = 0;
        foreach ($passengerLocations as $location) {
            if ($location['speed_kmh']) {
                $avgSpeed += $location['speed_kmh'];
                $speedCount++;
            }
        }
        $avgSpeed = $speedCount > 0 ? $avgSpeed / $speedCount : 0;

        return [
            'latitude' => $centroidLat,
            'longitude' => $centroidLon,
            'nearest_station_id' => $nearestStation['station_id'] ?? null,
            'distance_to_station_km' => $nearestStation['distance'] ?? null,
            'average_speed_kmh' => round($avgSpeed, 1),
            'sample_size' => $count,
        ];
    }

    /**
     * Get expected position based on schedule
     */
    protected function getExpectedPosition(TrainTrip $trip): ?array
    {
        $stops = $trip->train->stops->sortBy('stop_number');
        $currentTime = now();

        $expectedStation = null;
        $expectedStop = null;

        foreach ($stops as $stop) {
            $stopTime = Carbon::parse($trip->trip_date->format('Y-m-d') . ' ' . $stop->departure_time);

            if ($currentTime->lte($stopTime)) {
                $expectedStop = $stop;
                $expectedStation = $stop->station;
                break;
            }
        }

        if (!$expectedStation) {
            // Train should have completed journey
            $expectedStop = $stops->last();
            $expectedStation = $expectedStop?->station;
        }

        return [
            'station_id' => $expectedStation?->id,
            'station_name' => $expectedStation?->name,
            'stop_number' => $expectedStop?->stop_number,
            'scheduled_time' => $expectedStop?->departure_time,
        ];
    }

    /**
     * Calculate delay based on estimated vs expected position
     */
    protected function calculateDelay(TrainTrip $trip, ?array $estimated, ?array $expected, array $communityReports): ?int
    {
        $delays = [];

        // 1. GPS-based delay estimation
        if ($estimated && $expected && $estimated['nearest_station_id'] && $expected['station_id']) {
            $estimatedStopNumber = $this->getStopNumber($trip, $estimated['nearest_station_id']);
            $expectedStopNumber = $expected['stop_number'];

            if ($estimatedStopNumber && $expectedStopNumber) {
                $stopsDifference = $expectedStopNumber - $estimatedStopNumber;

                // If train is behind schedule (lower stop number than expected)
                if ($stopsDifference > 0) {
                    // Estimate 3-5 minutes delay per missed stop
                    $gpsDelay = $stopsDifference * 4;
                    $delays[] = ['value' => $gpsDelay, 'weight' => 2]; // GPS data has high weight
                }
            }
        }

        // 2. Community-reported delays
        if (!empty($communityReports)) {
            $avgCommunityDelay = 0;
            $totalWeight = 0;

            foreach ($communityReports as $report) {
                // Weight by verification count and recency
                $age = now()->diffInMinutes($report['timestamp']);
                $recencyWeight = max(0.1, 1 - ($age / 120)); // Decay over 2 hours
                $verificationWeight = min(1, $report['verification_count'] / 5);
                $weight = $recencyWeight * $verificationWeight;

                $avgCommunityDelay += $report['delay_minutes'] * $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight > 0) {
                $communityDelay = $avgCommunityDelay / $totalWeight;
                $delays[] = ['value' => $communityDelay, 'weight' => 1.5]; // Community data has medium weight
            }
        }

        // 3. Historical delay (current trip delay)
        if ($trip->delay_minutes > 0) {
            $delays[] = ['value' => $trip->delay_minutes, 'weight' => 0.5]; // Low weight for historical
        }

        // Calculate weighted average
        if (empty($delays)) {
            return 0;
        }

        $totalWeightedDelay = 0;
        $totalWeight = 0;

        foreach ($delays as $delay) {
            $totalWeightedDelay += $delay['value'] * $delay['weight'];
            $totalWeight += $delay['weight'];
        }

        return round($totalWeightedDelay / $totalWeight);
    }

    /**
     * Calculate confidence level of estimation
     */
    protected function calculateConfidence(array $passengerLocations, array $communityReports): string
    {
        $score = 0;

        // GPS data contribution (max 50 points)
        $gpsScore = min(50, count($passengerLocations) * 10);
        $score += $gpsScore;

        // Community reports contribution (max 30 points)
        $communityScore = min(30, count($communityReports) * 6);
        $score += $communityScore;

        // Recency contribution (max 20 points)
        if (!empty($passengerLocations)) {
            $latestUpdate = collect($passengerLocations)->max('timestamp');
            $age = now()->diffInSeconds($latestUpdate);
            $recencyScore = max(0, 20 - ($age / 15)); // Decay over 5 minutes
            $score += $recencyScore;
        }

        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }

    /**
     * Find nearest station to given coordinates
     */
    protected function findNearestStation(TrainTrip $trip, float $lat, float $lon): array
    {
        $stops = $trip->train->stops->sortBy('stop_number');
        $nearestStation = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stops as $stop) {
            $station = $stop->station;
            $distance = $this->calculateDistance($lat, $lon, $station->latitude, $station->longitude);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestStation = [
                    'station_id' => $station->id,
                    'stop_number' => $stop->stop_number,
                    'distance' => $distance,
                ];
            }
        }

        return $nearestStation ?? [];
    }

    /**
     * Get stop number for a station in this trip
     */
    protected function getStopNumber(TrainTrip $trip, int $stationId): ?int
    {
        $stop = $trip->train->stops->firstWhere('station_id', $stationId);
        return $stop?->stop_number;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
