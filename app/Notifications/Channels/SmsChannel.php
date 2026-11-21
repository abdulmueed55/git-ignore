<?php

namespace App\Notifications\Channels;

use App\Services\Attendance\LogSmsGateway;
use Illuminate\Notifications\Notification;

/** Sends a notification's toSms() text through the configured SMS gateway. */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->phone ?? null;
        if (! $to || ! method_exists($notification, 'toSms')) {
            return;
        }
        LogSmsGateway::resolve()->send($to, $notification->toSms($notifiable));
    }
}
