<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Each module keeps its own route file in routes/modules so modules stay
| independent:
|   portal.php     public landing page (reads CMS content)
|   apply.php      public applicant portal (apply, upload documents, status)
|   admissions.php admissions desk, AI verification, merit lists
|   attendance.php marking, analytics, defaulters, leaves, alerts
|   academics.php  programs, sections, subjects, timetable, students, staff, system
|   studio.php     website CMS ("Website studio")
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('role:principal,admission_officer,teacher,editor') // editors are redirected to the studio
        ->name('dashboard');

    // The bell: class alerts and other staff notifications.
    Route::middleware('role:principal,admission_officer,teacher,editor')->group(function () {
        Route::get('/notifications/feed', [NotificationController::class, 'feed'])->middleware('throttle:120,1')->name('notifications.feed');
        Route::post('/notifications/read', [NotificationController::class, 'read'])->middleware('throttle:60,1')->name('notifications.read');
    });
});

foreach (['portal', 'apply', 'admissions', 'attendance', 'academics', 'studio', 'student'] as $module) {
    $file = __DIR__."/modules/{$module}.php";
    if (is_file($file)) {
        require $file;
    }
}
