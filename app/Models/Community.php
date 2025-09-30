<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Community extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'trip_id',
        'name',
        'member_count',
        'message_count',
        'status',
        'auto_archive_at',
    ];

    public $translatable = ['name'];

    protected $casts = [
        'auto_archive_at' => 'datetime',
        'member_count' => 'integer',
        'message_count' => 'integer',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Train\TrainTrip::class, 'trip_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunityMessage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}