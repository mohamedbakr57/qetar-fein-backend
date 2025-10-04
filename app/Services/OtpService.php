<?php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OtpService
{
    public function sendOtp(string $phone): array
    {
        // Generate 6-digit OTP
        $otpCode = sprintf("%06d", mt_rand(100000, 999999));

        // Store OTP in database
        OtpVerification::create([
            'phone' => $phone,
            'otp_code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(5),
            'attempts' => 0
        ]);

        // In production, integrate with SMS service (Twilio, AWS SNS, etc.)
        // For development, log the OTP
        Log::info("OTP for phone {$phone}: {$otpCode}");

        // TODO: Implement actual SMS sending
        // Example:
        // $this->sendSms($phone, "Your Qatar Fein verification code is: {$otpCode}");

        return [
            'success' => true,
            'message' => 'OTP sent successfully',
            'otp_code' => config('app.debug') ? $otpCode : null // Only in debug mode
        ];
    }

    public function verifyOtp(string $phone, string $otpCode): array
    {
        $verification = OtpVerification::where('phone', $phone)
            ->where('verified_at', null)
            ->orderBy('created_at', 'desc')
            ->first();
        if (!$verification) {
            return [
                'success' => false,
                'message' => 'No OTP found for this phone number'
            ];
        }

        // Check if OTP is expired
        if ($verification->expires_at < now()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.'
            ];
        }

        // Check attempt limit
        if ($verification->attempts >= 3) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new OTP.'
            ];
        }

        // Verify OTP code
        if ($verification->otp_code !== $otpCode) {
            $verification->increment('attempts');
            return [
                'success' => false,
                'message' => 'Invalid OTP code'
            ];
        }

        // Mark as verified
        $verification->update(['verified_at' => now()]);

        return [
            'success' => true,
            'message' => 'OTP verified successfully'
        ];
    }

    private function sendSms(string $phone, string $message): bool
    {
        // TODO: Implement SMS sending logic
        // Example for Twilio:
        /*
        try {
            $twilio = new Client(config('services.twilio.sid'), config('services.twilio.token'));
            $twilio->messages->create($phone, [
                'from' => config('services.twilio.from'),
                'body' => $message
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
        */

        return true; // Mock success for now
    }
}