<?php

namespace App\Models\Train;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoStop extends Model
{
    use HasFactory;

    protected $table = 'no_stops';

    protected $fillable = [
        'train_id',
        'stop_number',
        'reason',
    ];

    protected $casts = [
        'stop_number' => 'integer',
    ];

    public function train(): BelongsTo
    {
        return $this->belongsTo(Train::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('stop_number');
    }
}