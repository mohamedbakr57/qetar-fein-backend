<?php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Station extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'code',
        'name',
        'description',
        'latitude',
        'longitude',
        'elevation',
        'city',
        'region',
        'country_code',
        'timezone',
        'facilities',
        'status',
        'order_index',
    ];

    protected $translatable = [
        'name',
        'description', 
        'city',
        'region'
    ];

    protected $casts = [
        'facilities' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // Relationships
    public function routeStations()
    {
        return $this->hasMany(RouteStation::class);
    }

    public function boardingAssignments()
    {
        return $this->hasMany(PassengerAssignment::class, 'boarding_station_id');
    }

    public function destinationAssignments()
    {
        return $this->hasMany(PassengerAssignment::class, 'destination_station_id');
    }

    public function communityMessages()
    {
        return $this->hasMany(\App\Models\Community\CommunityMessage::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrderedByIndex($query)
    {
        return $query->orderBy('order_index');
    }

    // Helper Methods
    public function getDistanceFromCoordinates($latitude, $longitude)
    {
        $earthRadius = 6371; // km

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($latitude);
        $lon2 = deg2rad($longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function hasFacility($facility)
    {
        return in_array($facility, $this->facilities ?? []);
    }
}