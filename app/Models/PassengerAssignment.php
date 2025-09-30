<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassengerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trip_id',
        'boarding_station_id',
        'destination_station_id',
        'current_latitude',
        'current_longitude',
        'location_accuracy',
        'speed_kmh',
        'heading',
        'location_sharing_enabled',
        'status',
        'started_at',
        'completed_at',
        'cancelled_at',
        'reward_points_earned',
        'last_location_update',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'location_accuracy' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'heading' => 'integer',
        'location_sharing_enabled' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reward_points_earned' => 'integer',
        'last_location_update' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Train\TrainTrip::class, 'trip_id');
    }

    public function boardingStation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Train\Station::class, 'boarding_station_id');
    }

    public function destinationStation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Train\Station::class, 'destination_station_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'success',
            'completed' => 'primary',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInMinutes($this->completed_at);
        }
        return null;
    }
}