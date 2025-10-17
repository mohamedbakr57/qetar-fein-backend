<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\PassengerAssignment;
use App\Models\CommunityMessage;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class UserStatsController extends Controller
{
    use ApiResponse;

    /**
     * Get user statistics
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Total trips
        $totalTrips = PassengerAssignment::where('user_id', $user->id)->count();
        $completedTrips = PassengerAssignment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
        $activeTrips = PassengerAssignment::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        // Calculate total distance traveled (approximate)
        $completedAssignments = PassengerAssignment::with(['boardingStation', 'destinationStation'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        $totalDistance = 0;
        foreach ($completedAssignments as $assignment) {
            if ($assignment->boardingStation && $assignment->destinationStation) {
                $distance = $this->calculateDistance(
                    $assignment->boardingStation->latitude,
                    $assignment->boardingStation->longitude,
                    $assignment->destinationStation->latitude,
                    $assignment->destinationStation->longitude
                );
                $totalDistance += $distance;
            }
        }

        // Community contributions
        $messagesPosted = CommunityMessage::where('user_id', $user->id)->count();
        $verifiedMessages = CommunityMessage::where('user_id', $user->id)
            ->where('is_verified', true)
            ->count();

        // Time stats
        $firstTrip = PassengerAssignment::where('user_id', $user->id)
            ->orderBy('created_at')
            ->first();

        $memberSince = $firstTrip ? $firstTrip->created_at : $user->created_at;

        // Recent activity
        $recentTrips = PassengerAssignment::with(['trip.train', 'boardingStation', 'destinationStation'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Badges and rewards
        $badgesCount = $user->badges()->count();
        $rewardPoints = $user->reward_points ?? 0;

        // Calculate rank/level
        $rank = $this->calculateUserRank($rewardPoints);

        // Most used stations
        $mostUsedStations = PassengerAssignment::selectRaw('boarding_station_id as station_id, COUNT(*) as count')
            ->where('user_id', $user->id)
            ->groupBy('boarding_station_id')
            ->orderByDesc('count')
            ->limit(3)
            ->with('boardingStation')
            ->get()
            ->map(function($item) {
                return [
                    'station' => $item->boardingStation,
                    'trip_count' => $item->count
                ];
            });

        return $this->apiResponse([
            'trips' => [
                'total' => $totalTrips,
                'completed' => $completedTrips,
                'active' => $activeTrips,
                'cancelled' => $totalTrips - $completedTrips - $activeTrips,
            ],
            'distance' => [
                'total_km' => round($totalDistance, 2),
                'average_per_trip' => $completedTrips > 0 ? round($totalDistance / $completedTrips, 2) : 0,
            ],
            'community' => [
                'messages_posted' => $messagesPosted,
                'verified_messages' => $verifiedMessages,
                'verification_rate' => $messagesPosted > 0 ? round(($verifiedMessages / $messagesPosted) * 100, 1) : 0,
            ],
            'gamification' => [
                'reward_points' => $rewardPoints,
                'badges_count' => $badgesCount,
                'rank' => $rank,
                'next_rank_points' => $this->getNextRankPoints($rewardPoints),
            ],
            'activity' => [
                'member_since' => $memberSince->toISOString(),
                'days_active' => $memberSince->diffInDays(now()),
                'recent_trips' => $recentTrips,
                'most_used_stations' => $mostUsedStations,
            ],
        ]);
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
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

    /**
     * Calculate user rank based on points
     */
    private function calculateUserRank(int $points): array
    {
        $ranks = [
            ['name' => ['ar' => 'مبتدئ', 'en' => 'Novice'], 'min_points' => 0],
            ['name' => ['ar' => 'مسافر', 'en' => 'Traveler'], 'min_points' => 100],
            ['name' => ['ar' => 'مستكشف', 'en' => 'Explorer'], 'min_points' => 500],
            ['name' => ['ar' => 'خبير', 'en' => 'Expert'], 'min_points' => 1000],
            ['name' => ['ar' => 'أسطورة', 'en' => 'Legend'], 'min_points' => 5000],
        ];

        $currentRank = $ranks[0];
        foreach ($ranks as $rank) {
            if ($points >= $rank['min_points']) {
                $currentRank = $rank;
            }
        }

        return $currentRank;
    }

    /**
     * Get points needed for next rank
     */
    private function getNextRankPoints(int $currentPoints): ?int
    {
        $ranks = [0, 100, 500, 1000, 5000];

        foreach ($ranks as $rankPoints) {
            if ($currentPoints < $rankPoints) {
                return $rankPoints;
            }
        }

        return null; // Max rank achieved
    }
}
