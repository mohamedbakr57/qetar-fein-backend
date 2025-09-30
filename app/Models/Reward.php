<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'points_earned',
        'description',
        'reference_id',
        'reference_type',
        'claimed_at',
    ];

    protected $casts = [
        'points_earned' => 'integer',
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeClaimed($query)
    {
        return $query->whereNotNull('claimed_at');
    }

    public function scopeUnclaimed($query)
    {
        return $query->whereNull('claimed_at');
    }
}