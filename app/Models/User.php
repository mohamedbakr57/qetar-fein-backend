<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone',
        'phone_verified_at',
        'email',
        'email_verified_at',
        'name',
        'avatar',
        'date_of_birth',
        'gender',
        'preferred_language',
        'notification_preferences',
        'ad_free_until',
        'total_assignments',
        'successful_assignments',
        'reward_points',
        'status',
        'last_active_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'notification_preferences' => 'array',
            'ad_free_until' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    // Relationships
    public function socialLogins()
    {
        return $this->hasMany(SocialLogin::class);
    }

    public function passengerAssignments()
    {
        return $this->hasMany(PassengerAssignment::class);
    }

    public function badges()
    {
        return $this->belongsToManyThrough(Badge::class, UserBadge::class, 'user_id', 'badge_id');
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function rewards()
    {
        return $this->hasMany(Reward::class);
    }

    public function communityMessages()
    {
        return $this->hasMany(CommunityMessage::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('phone_verified_at');
    }

    // Accessors & Mutators
    public function getIsAdFreeAttribute()
    {
        return $this->ad_free_until && $this->ad_free_until->isFuture();
    }

    public function getAssignmentSuccessRateAttribute()
    {
        return $this->total_assignments > 0 
            ? round(($this->successful_assignments / $this->total_assignments) * 100, 2)
            : 0;
    }

    // Helper Methods
    public function addRewardPoints(int $points, string $type, string $description, $reference = null)
    {
        $this->increment('reward_points', $points);
        
        return $this->rewards()->create([
            'type' => $type,
            'points_earned' => $points,
            'description' => ['ar' => $description, 'en' => $description],
            'reference_id' => $reference?->id,
            'reference_type' => $reference ? get_class($reference) : null,
        ]);
    }

    public function awardAdFreePeriod(int $weeks = 1)
    {
        $currentExpiry = $this->ad_free_until ?: now();
        $newExpiry = max($currentExpiry, now())->addWeeks($weeks);
        
        $this->update(['ad_free_until' => $newExpiry]);
        
        return $this->addRewardPoints(
            0,
            'ad_free_period',
            "Ad-free period extended by {$weeks} week(s)"
        );
    }

    public function assignToBadge($badge)
    {
        if (!$this->userBadges()->where('badge_id', $badge->id)->exists()) {
            $this->userBadges()->create([
                'badge_id' => $badge->id,
                'earned_at' => now(),
            ]);

            if ($badge->points_reward > 0) {
                $this->addRewardPoints(
                    $badge->points_reward,
                    'badge_earned',
                    "Earned badge: {$badge->name}",
                    $badge
                );
            }

            return true;
        }

        return false;
    }
}
