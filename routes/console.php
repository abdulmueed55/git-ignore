<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Teacher class alerts (morning list + a few minutes before each class). Needs the scheduler:
// `* * * * * php artisan schedule:run` on a server, or `php artisan schedule:work`.
Schedule::command('classes:remind')->everyMinute()->withoutOverlapping();
