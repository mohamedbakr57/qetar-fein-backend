<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'otp_code',
        'expires_at',
        'verified_at',
        'attempts'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer'
    ];

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    public function canAttempt(): bool
    {
        return $this->attempts < 3;
    }
}