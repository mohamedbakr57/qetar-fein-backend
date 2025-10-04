<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\RewardService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RewardController extends Controller
{
    use ApiResponse;

    protected $rewardService;

    public function __construct(RewardService $rewardService)
    {
        $this->rewardService = $rewardService;
    }

    public function myRewards(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Reward::where('user_id', $user->id);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $rewards = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get summary
        $summary = $this->rewardService->getUserRewardsSummary($user);

        return $this->apiResponse([
            'rewards' => $rewards->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $rewards->currentPage(),
                'last_page' => $rewards->lastPage(),
                'per_page' => $rewards->perPage(),
                'total' => $rewards->total(),
            ]
        ]);
    }

    public function myBadges(Request $request): JsonResponse
    {
        $user = $request->user();

        $userBadges = UserBadge::with('badge')
            ->where('user_id', $user->id)
            ->orderBy('earned_at', 'desc')
            ->get();

        // Get available badges user hasn't earned yet
        $earnedBadgeIds = $userBadges->pluck('badge_id');
        $availableBadges = Badge::whereNotIn('id', $earnedBadgeIds)
            ->where('is_active', true)
            ->orderBy('rarity', 'asc')
            ->get();

        return $this->apiResponse([
            'earned_badges' => $userBadges->map(function($userBadge) {
                return [
                    'badge' => $userBadge->badge,
                    'earned_at' => $userBadge->earned_at,
                ];
            }),
            'available_badges' => $availableBadges,
            'earned_count' => $userBadges->count(),
            'total_available' => Badge::where('is_active', true)->count(),
        ]);
    }

    public function badgeDetails(int $id): JsonResponse
    {
        $badge = Badge::find($id);

        if (!$badge) {
            return $this->errorResponse('Badge not found', 404);
        }

        // Get badge statistics
        $totalEarned = UserBadge::where('badge_id', $badge->id)->count();
        $earnedThisMonth = UserBadge::where('badge_id', $badge->id)
            ->where('earned_at', '>=', now()->startOfMonth())
            ->count();

        return $this->apiResponse([
            'badge' => $badge,
            'statistics' => [
                'total_earned' => $totalEarned,
                'earned_this_month' => $earnedThisMonth,
                'rarity_percentage' => $totalEarned > 0 ? round(($totalEarned / 1000) * 100, 2) : 0, // Assuming 1000 total users
            ]
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $type = $request->get('type', 'points'); // points, assignments, badges
        $timeframe = $request->get('timeframe', 'all_time'); // all_time, this_month, this_week

        $query = \App\Models\User::with(['badges']);

        // Apply timeframe filters
        switch ($timeframe) {
            case 'this_month':
                $startDate = now()->startOfMonth();
                break;
            case 'this_week':
                $startDate = now()->startOfWeek();
                break;
            default:
                $startDate = null;
        }

        switch ($type) {
            case 'points':
                if ($startDate) {
                    // Calculate points from rewards in timeframe
                    $query->withSum(['rewards as period_points' => function($query) use ($startDate) {
                        $query->where('created_at', '>=', $startDate);
                    }], 'points_earned')
                    ->orderBy('period_points', 'desc');
                } else {
                    $query->orderBy('reward_points', 'desc');
                }
                break;

            case 'assignments':
                if ($startDate) {
                    $query->withCount(['passengerAssignments as period_assignments' => function($query) use ($startDate) {
                        $query->where('status', 'completed')
                              ->where('completed_at', '>=', $startDate);
                    }])
                    ->orderBy('period_assignments', 'desc');
                } else {
                    $query->withCount(['passengerAssignments as total_assignments' => function($query) {
                        $query->where('status', 'completed');
                    }])
                    ->orderBy('total_assignments', 'desc');
                }
                break;

            case 'badges':
                if ($startDate) {
                    $query->withCount(['badges as period_badges' => function($query) use ($startDate) {
                        $query->where('user_badges.earned_at', '>=', $startDate);
                    }])
                    ->orderBy('period_badges', 'desc');
                } else {
                    $query->withCount('badges as total_badges')
                          ->orderBy('total_badges', 'desc');
                }
                break;
        }

        $users = $query->where('status', 'active')
                      ->limit(100)
                      ->get();

        $leaderboard = $users->map(function($user, $index) use ($type, $timeframe) {
            $value = 0;

            switch ($type) {
                case 'points':
                    $value = $timeframe === 'all_time' ? $user->reward_points : ($user->period_points ?? 0);
                    break;
                case 'assignments':
                    $value = $timeframe === 'all_time' ? ($user->total_assignments ?? 0) : ($user->period_assignments ?? 0);
                    break;
                case 'badges':
                    $value = $timeframe === 'all_time' ? ($user->total_badges ?? 0) : ($user->period_badges ?? 0);
                    break;
            }

            return [
                'rank' => $index + 1,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? 'Anonymous User',
                    'avatar' => $user->avatar,
                ],
                'value' => $value,
                'recent_badges' => $user->badges->take(3),
            ];
        });

        return $this->apiResponse([
            'leaderboard' => $leaderboard,
            'type' => $type,
            'timeframe' => $timeframe,
            'total_participants' => $users->count(),
        ]);
    }

    public function allBadges(): JsonResponse
    {
        $badges = Badge::where('is_active', true)
                      ->orderBy('category')
                      ->orderBy('rarity', 'asc')
                      ->get()
                      ->groupBy('category');

        return $this->apiResponse([
            'badges_by_category' => $badges,
            'total_badges' => Badge::where('is_active', true)->count(),
        ]);
    }

    public function rewardsSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->rewardService->getUserRewardsSummary($user);

        // Add recent activity
        $recentRewards = Reward::where('user_id', $user->id)
                              ->orderBy('created_at', 'desc')
                              ->limit(5)
                              ->get();

        $recentBadges = UserBadge::with('badge')
                               ->where('user_id', $user->id)
                               ->orderBy('earned_at', 'desc')
                               ->limit(3)
                               ->get();

        return $this->apiResponse([
            'summary' => $summary,
            'recent_rewards' => $recentRewards,
            'recent_badges' => $recentBadges->map(function($userBadge) {
                return [
                    'badge' => $userBadge->badge,
                    'earned_at' => $userBadge->earned_at,
                ];
            }),
        ]);
    }
}