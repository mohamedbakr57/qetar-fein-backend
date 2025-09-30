<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'verification_type',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunityMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeConfirmations($query)
    {
        return $query->where('verification_type', 'confirm');
    }

    public function scopeDisputes($query)
    {
        return $query->where('verification_type', 'dispute');
    }
}