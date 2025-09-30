<?php

namespace App\Services;

use App\Models\PassengerAssignment;
use App\Models\Reward;
use App\Models\User;
use App\Models\Badge;
use App\Models\UserBadge;
use Carbon\Carbon;

class RewardService
{
    const ASSIGNMENT_POINTS = 10;
    const ASSIGNMENTS_FOR_AD_FREE = 10;
    const AD_FREE_DURATION_DAYS = 7;

    public function processAssignmentCompletion(PassengerAssignment $assignment): array
    {
        $user = $assignment->user;
        $rewards = [];

        // Award points for assignment completion
        $pointsReward = $this->awardPoints($user, self::ASSIGNMENT_POINTS, 'assignment_completion', $assignment->id);
        $rewards[] = $pointsReward;

        // Check for ad-free reward
        $completedAssignments = PassengerAssignment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        if ($completedAssignments % self::ASSIGNMENTS_FOR_AD_FREE === 0) {
            $adFreeReward = $this->awardAdFreeWeek($user, $assignment->id);
            $rewards[] = $adFreeReward;
        }

        // Check for badge eligibility
        $badges = $this->checkBadgeEligibility($user);
        $rewards = array_merge($rewards, $badges);

        return $rewards;
    }

    public function awardPoints(User $user, int $points, string $type, ?int $referenceId = null): Reward
    {
        $reward = Reward::create([
            'user_id' => $user->id,
            'type' => $type,
            'points_earned' => $points,
            'description' => "Earned {$points} points for {$type}",
            'reference_id' => $referenceId,
            'claimed_at' => now(),
        ]);

        // Update user total points
        $user->increment('reward_points', $points);

        return $reward;
    }

    public function awardAdFreeWeek(User $user, int $referenceId): Reward
    {
        $currentAdFreeUntil = $user->ad_free_until ? Carbon::parse($user->ad_free_until) : now();
        $newAdFreeUntil = $currentAdFreeUntil->addDays(self::AD_FREE_DURATION_DAYS);

        $user->update(['ad_free_until' => $newAdFreeUntil]);

        return Reward::create([
            'user_id' => $user->id,
            'type' => 'ad_free_week',
            'points_earned' => 0,
            'description' => 'Earned 1 week ad-free period',
            'reference_id' => $referenceId,
            'claimed_at' => now(),
        ]);
    }

    public function checkBadgeEligibility(User $user): array
    {
        $badges = [];
        $completedAssignments = PassengerAssignment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        // Check assignment-based badges
        $assignmentBadges = [
            ['count' => 1, 'badge_slug' => 'first_trip'],
            ['count' => 5, 'badge_slug' => 'regular_traveler'],
            ['count' => 10, 'badge_slug' => 'frequent_traveler'],
            ['count' => 25, 'badge_slug' => 'train_enthusiast'],
            ['count' => 50, 'badge_slug' => 'rail_master'],
            ['count' => 100, 'badge_slug' => 'train_legend'],
        ];

        foreach ($assignmentBadges as $badgeData) {
            if ($completedAssignments >= $badgeData['count']) {
                $badge = Badge::where('slug', $badgeData['badge_slug'])->first();
                if ($badge && !$user->badges()->where('badge_id', $badge->id)->exists()) {
                    $userBadge = UserBadge::create([
                        'user_id' => $user->id,
                        'badge_id' => $badge->id,
                        'earned_at' => now(),
                    ]);

                    $badges[] = [
                        'type' => 'badge',
                        'badge' => $badge,
                        'earned_at' => $userBadge->earned_at,
                    ];
                }
            }
        }

        // Check location sharing badge
        $locationSharingCount = PassengerAssignment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('location_sharing_enabled', true)
            ->count();

        if ($locationSharingCount >= 5) {
            $badge = Badge::where('slug', 'location_sharer')->first();
            if ($badge && !$user->badges()->where('badge_id', $badge->id)->exists()) {
                $userBadge = UserBadge::create([
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'earned_at' => now(),
                ]);

                $badges[] = [
                    'type' => 'badge',
                    'badge' => $badge,
                    'earned_at' => $userBadge->earned_at,
                ];
            }
        }

        return $badges;
    }

    public function getUserRewardsSummary(User $user): array
    {
        $completedAssignments = PassengerAssignment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $assignmentsToAdFree = self::ASSIGNMENTS_FOR_AD_FREE - ($completedAssignments % self::ASSIGNMENTS_FOR_AD_FREE);
        $assignmentsToAdFree = $assignmentsToAdFree === self::ASSIGNMENTS_FOR_AD_FREE ? 0 : $assignmentsToAdFree;

        return [
            'total_points' => $user->reward_points,
            'completed_assignments' => $completedAssignments,
            'assignments_to_ad_free' => $assignmentsToAdFree,
            'ad_free_until' => $user->ad_free_until,
            'is_ad_free' => $user->ad_free_until && Carbon::parse($user->ad_free_until)->isFuture(),
            'badges_count' => $user->badges()->count(),
        ];
    }
}