<?php

use App\Http\Controllers\Academics\ActivityController;
use App\Http\Controllers\Academics\ProgramController;
use App\Http\Controllers\Academics\SectionController;
use App\Http\Controllers\Academics\StaffController;
use App\Http\Controllers\Academics\StudentController;
use App\Http\Controllers\Academics\SubjectController;
use App\Http\Controllers\Academics\TimetableController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // ---------- Students ----------
    Route::middleware('role:principal,admission_officer,teacher')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/export', [StudentController::class, 'export'])->name('students.export');
        Route::middleware('role:principal,admission_officer')->group(function () {
            Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
            Route::post('/students', [StudentController::class, 'store'])->name('students.store');
            Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
            Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        });
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/card', [StudentController::class, 'card'])->name('students.card');
    });

    // ---------- Academic setup ----------
    Route::prefix('academics')->name('academics.')->group(function () {
        Route::middleware('role:principal')->group(function () {
            Route::resource('programs', ProgramController::class)->except(['destroy']);
            Route::resource('sections', SectionController::class)->except(['destroy']);
            Route::resource('subjects', SubjectController::class)->except(['show', 'destroy']);

            Route::post('/timetable/slots', [TimetableController::class, 'storeSlot'])->name('timetable.slots.store');
            Route::put('/timetable/slots/{slot}', [TimetableController::class, 'updateSlot'])->name('timetable.slots.update');
            Route::delete('/timetable/slots/{slot}', [TimetableController::class, 'destroySlot'])->name('timetable.slots.destroy');
            Route::post('/timetable/courses', [TimetableController::class, 'storeCourse'])->name('timetable.courses.store');
            Route::put('/timetable/courses/{course}/teacher', [TimetableController::class, 'assignTeacher'])->name('timetable.courses.teacher');
        });
        Route::get('/timetable', [TimetableController::class, 'index'])
            ->middleware('role:principal,teacher')->name('timetable');
    });

    // ---------- Staff ----------
    Route::middleware('role:principal')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::post('/staff/{staff}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.reset-password');

        Route::get('/system/activity', ActivityController::class)->name('system.activity');
    });
});
