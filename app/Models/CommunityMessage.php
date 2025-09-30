<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'user_id',
        'guest_id',
        'guest_name',
        'station_id',
        'time_passed_minutes',
        'message_type',
        'additional_data',
        'is_verified',
        'verification_count',
    ];

    protected $casts = [
        'additional_data' => 'array',
        'is_verified' => 'boolean',
        'verification_count' => 'integer',
        'time_passed_minutes' => 'integer',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Train\Station::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(MessageVerification::class, 'message_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('message_type', $type);
    }
}