<?php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class TrainType extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'max_speed_kmh',
        'typical_capacity',
        'comfort_level',
        'features',
    ];

    protected $translatable = [
        'name',
        'description'
    ];

    protected $casts = [
        'features' => 'array',
    ];

    // Relationships
    public function trains()
    {
        return $this->hasMany(Train::class, 'train_type_id');
    }
}
