<?php

namespace App\Services\Attendance;

/**
 * Anything that can deliver a text message to a Pakistani mobile number.
 * Implement this for a real provider (e.g. a branded-SMS API) and register it
 * under config('attendance.sms.drivers').
 */
interface SmsGateway
{
    /** @return array{ok: bool, error?: string} */
    public function send(string $to, string $message): array;

    public static function make(): self;
}
