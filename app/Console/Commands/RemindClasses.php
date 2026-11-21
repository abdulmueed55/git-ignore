<?php

namespace App\Console\Commands;

use App\Services\Timetable\ClassReminders;
use Illuminate\Console\Command;

class RemindClasses extends Command
{
    protected $signature = 'classes:remind';

    protected $description = 'Alert teachers about today\'s classes and each class shortly before it starts';

    public function handle(ClassReminders $reminders): int
    {
        $n = $reminders->run();
        $this->info("{$n} class alert(s) sent.");

        return self::SUCCESS;
    }
}
