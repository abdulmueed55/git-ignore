<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Notification;

/**
 * A message in a staff member's bell: class starting soon, today's classes, extra class
 * scheduled or cancelled. `key` makes each alert unique so it is never sent twice.
 */
class StaffAlert extends Notification
{
    public function __construct(
        public string $key,
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
        public bool $sms = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sms && filled($notifiable->phone ?? null) ? ['database', SmsChannel::class] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['key' => $this->key, 'kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toSms(object $notifiable): string
    {
        return 'FGLA College: '.$this->title.' — '.$this->body;
    }
}
