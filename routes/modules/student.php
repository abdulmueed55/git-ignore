<?php

use App\Http\Controllers\Student\AuthController;
use App\Http\Controllers\Student\LeaveController;
use App\Http\Controllers\Student\PasswordController;
use App\Http\Controllers\Student\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Student portal. Students sign in with roll number + password (first time: the PIN of
| their admission application). Separate "student" guard: staff routes never accept it.
*/
Route::prefix('student')->name('student.')->group(function () {
    Route::middleware('guest:student')->group(function () {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.store');
    });

    Route::middleware('auth:student')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

        Route::middleware('student.ready')->group(function () {
            Route::get('/', [PortalController::class, 'home'])->name('home');
            Route::get('/attendance', [PortalController::class, 'attendance'])->name('attendance');
            Route::get('/timetable', [PortalController::class, 'timetable'])->name('timetable');
            Route::get('/application', [PortalController::class, 'application'])->name('application');
            Route::get('/application/documents/{type}', [PortalController::class, 'file'])->name('application.file');
            Route::get('/leave', [LeaveController::class, 'index'])->name('leave');
            Route::post('/leave', [LeaveController::class, 'store'])->middleware('throttle:10,1')->name('leave.store');
        });
    });
});
