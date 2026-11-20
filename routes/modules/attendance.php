<?php

use App\Http\Controllers\Attendance\AlertController;
use App\Http\Controllers\Attendance\AnalyticsController;
use App\Http\Controllers\Attendance\DefaulterController;
use App\Http\Controllers\Attendance\LeaveController;
use App\Http\Controllers\Attendance\MarkController;
use App\Http\Controllers\Attendance\RegisterController;
use App\Http\Controllers\Attendance\StudentAttendanceController;
use App\Http\Controllers\Attendance\TodayController;
use App\Http\Controllers\Attendance\VoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:principal,teacher'])->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/', TodayController::class)->name('index');

    Route::get('/courses/{course}/mark', [MarkController::class, 'show'])->name('mark');
    Route::post('/courses/{course}/mark', [MarkController::class, 'store'])->name('save');
    Route::post('/courses/{course}/voice', VoiceController::class)->middleware('throttle:30,1')->name('voice');

    Route::get('/analytics', AnalyticsController::class)->name('analytics');

    Route::get('/defaulters', [DefaulterController::class, 'index'])->name('defaulters');
    Route::get('/defaulters/export', [DefaulterController::class, 'export'])->name('defaulters.export');
    Route::post('/defaulters/notify', [DefaulterController::class, 'notify'])->name('defaulters.notify');

    Route::get('/students/{student}', StudentAttendanceController::class)->name('student');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::get('/register/export', [RegisterController::class, 'export'])->name('register.export');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::patch('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::patch('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');

    Route::middleware('role:principal')->group(function () {
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
        Route::post('/alerts/send', [AlertController::class, 'send'])->name('alerts.send');
    });
});
