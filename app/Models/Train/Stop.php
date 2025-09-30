<?php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stop extends Model
{
    use HasFactory;

    protected $fillable = [
        'train_id',
        'station_id',
        'stop_number',
        'arrival_time',
        'departure_time',
        'platform',
        'stop_duration_minutes',
        'is_major_stop',
        'notes',
    ];

    protected $casts = [
        'arrival_time' => 'datetime:H:i:s',
        'departure_time' => 'datetime:H:i:s',
        'stop_number' => 'integer',
        'stop_duration_minutes' => 'integer',
        'is_major_stop' => 'boolean',
    ];

    public function train(): BelongsTo
    {
        return $this->belongsTo(Train::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('stop_number');
    }

    public function scopeMajorStops($query)
    {
        return $query->where('is_major_stop', true);
    }

    public function getFormattedStopDurationAttribute(): string
    {
        return $this->stop_duration_minutes . ' min';
    }

    public function getIsOriginAttribute(): bool
    {
        return $this->stop_number === 1;
    }

    public function getIsDestinationAttribute(): bool
    {
        return $this->stop_number === $this->train->stops()->max('stop_number');
    }
}