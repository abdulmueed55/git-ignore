<?php

use App\Services\Attendance\LogSmsGateway;

return [
    // Minimum attendance (%) required to sit the board / semester exam.
    'threshold' => (int) env('ATTENDANCE_THRESHOLD', 75),

    // Students within this many points above the threshold are shown as "at risk".
    'warning_margin' => 5,

    // Teachers can edit a register for this many hours after the lecture starts; leadership can always edit.
    'lock_after_hours' => (int) env('ATTENDANCE_LOCK_HOURS', 48),

    // Queue a guardian alert after this many consecutive fully-absent days.
    'consecutive_absence_alert' => 3,

    // Percentage = (Present + Late) / (Present + Late + Absent). Approved leave (E) is excluded from both sides.
    'statuses' => ['P' => 'Present', 'A' => 'Absent', 'L' => 'Late', 'E' => 'Leave'],

    // Gazetted / college holidays (Y-m-d). No registers are expected on these days.
    'holidays' => [
        '2026-08-14' => 'Independence Day',
        '2026-08-26' => 'Eid Milad-un-Nabi',
        '2026-11-09' => 'Iqbal Day',
        '2026-12-25' => 'Quaid-e-Azam Day',
        '2027-02-05' => 'Kashmir Solidarity Day',
        '2027-03-23' => 'Pakistan Day',
    ],

    'sms' => [
        // log = write messages to the application log and mark them sent.
        // Add a gateway class (e.g. a Pakistani SMS provider) and map it here.
        'driver' => env('ATTENDANCE_SMS_DRIVER', 'log'),
        'drivers' => [
            'log' => LogSmsGateway::class,
        ],
        'sender' => env('ATTENDANCE_SMS_SENDER', 'FGLA-College'),
        'college_phone' => env('COLLEGE_PHONE', '051-5492888'),
    ],
];
