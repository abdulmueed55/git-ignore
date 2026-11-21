<?php

return [
    // Minutes before a class that its teacher is alerted ("Physics in 10 min, Room B-2").
    'reminder_minutes' => (int) env('CLASS_REMINDER_MINUTES', 10),

    // From this time each teacher gets the day's list of classes (regular + extra).
    'daily_summary_at' => env('CLASS_DAILY_SUMMARY_AT', '07:30'),

    // Also text the reminder to the teacher's phone (uses the SMS gateway in config/attendance.php).
    'sms_reminders' => (bool) env('CLASS_REMINDER_SMS', false),

    // How far ahead an extra class can be scheduled.
    'extra_max_days_ahead' => 60,
];
