<?php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Train extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'train_type_id',
        'number',
        'name',
        'description',
        'type',
        'operator',
        'capacity',
        'max_speed',
        'amenities',
        'status',
    ];

    protected $translatable = [
        'name',
        'description',
        'operator'
    ];

    protected $casts = [
        'amenities' => 'array',
    ];

    // Relationships according to business rules
    public function trainType()
    {
        return $this->belongsTo(TrainType::class, 'train_type_id');
    }

    public function stops()
    {
        return $this->hasMany(\App\Models\Train\Stop::class)->orderBy('stop_number');
    }

    public function noStops()
    {
        return $this->hasMany(\App\Models\Train\NoStop::class);
    }

    public function trips()
    {
        return $this->hasMany(TrainTrip::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Helper Methods
    public function hasAmenity($amenity)
    {
        return in_array($amenity, $this->amenities ?? []);
    }

    public function getCurrentTrip()
    {
        return $this->trips()
            ->where('trip_date', today())
            ->whereIn('status', ['boarding', 'departed', 'in_transit'])
            ->first();
    }
}