<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\Log;

/** Default driver: writes each SMS to the application log and reports success. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): array
    {
        $normalized = preg_replace('/\D+/', '', $to);
        if (strlen($normalized) < 10) {
            return ['ok' => false, 'error' => 'Invalid mobile number'];
        }

        Log::info('[sms] to '.$to.' from '.config('attendance.sms.sender').': '.$message);

        return ['ok' => true];
    }

    public static function make(): self
    {
        return new self;
    }

    /** Resolve the configured driver. */
    public static function resolve(): SmsGateway
    {
        $driver = config('attendance.sms.driver', 'log');
        $class = config("attendance.sms.drivers.$driver", self::class);

        return $class::make();
    }
}
