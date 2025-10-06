<?php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'train_id',
        'trip_date',
        'actual_departure_time',
        'actual_arrival_time',
        'estimated_departure_time',
        'estimated_arrival_time',
        'delay_minutes',
        'current_station_id',
        'next_station_id',
        'current_latitude',
        'current_longitude',
        'speed_kmh',
        'status',
        'passenger_count',
        'last_location_update',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'actual_departure_time' => 'datetime',
        'actual_arrival_time' => 'datetime',
        'estimated_departure_time' => 'datetime',
        'estimated_arrival_time' => 'datetime',
        'delay_minutes' => 'integer',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'speed_kmh' => 'decimal:2',
        'heading' => 'integer',
        'passenger_count' => 'integer',
    ];

    public function train(): BelongsTo
    {
        return $this->belongsTo(Train::class);
    }

    public function currentStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'current_station_id');
    }

    public function nextStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'next_station_id');
    }

    public function passengerAssignments(): HasMany
    {
        return $this->hasMany(\App\Models\PassengerAssignment::class, 'trip_id');
    }

    public function communities(): HasMany
    {
        return $this->hasMany(\App\Models\Community::class, 'trip_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('trip_date', today());
    }

    public function getIsDelayedAttribute(): bool
    {
        return $this->delay_minutes > 0;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'scheduled' => 'gray',
            'active' => 'success',
            'completed' => 'primary',
            'cancelled' => 'danger',
            'delayed' => 'warning',
            default => 'gray',
        };
    }
}