<?php

use App\Http\Controllers\Admissions\ApplicationController;
use App\Http\Controllers\Admissions\CycleController;
use App\Http\Controllers\Admissions\DeskController;
use App\Http\Controllers\Admissions\DocumentController;
use App\Http\Controllers\Admissions\MeritListController;
use App\Http\Controllers\Admissions\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:principal,admission_officer'])
    ->prefix('admissions')->name('admissions.')
    ->group(function () {
        Route::get('/', DeskController::class)->name('dashboard');

        Route::get('/applications', [ApplicationController::class, 'index'])->name('index');
        Route::get('/applications/export', [ApplicationController::class, 'export'])->name('export');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('show');
        Route::get('/applications/{application}/progress', [ApplicationController::class, 'progress'])->name('progress');
        Route::put('/applications/{application}', [ApplicationController::class, 'update'])->name('update');
        Route::patch('/applications/{application}/status', [ApplicationController::class, 'status'])->name('status');
        Route::post('/applications/{application}/reverify', [ApplicationController::class, 'reverify'])->name('reverify');
        Route::post('/applications/{application}/admit', [ApplicationController::class, 'admit'])->name('admit');
        Route::post('/applications/{application}/checks/{check}/override', [ApplicationController::class, 'override'])->name('checks.override');

        Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

        Route::get('/verification', VerificationController::class)->name('verification');

        Route::get('/merit-lists', [MeritListController::class, 'index'])->name('merit.index');
        Route::post('/merit-lists', [MeritListController::class, 'generate'])->name('merit.generate');
        Route::get('/merit-lists/{meritList}', [MeritListController::class, 'show'])->name('merit.show');
        Route::get('/merit-lists/{meritList}/print', [MeritListController::class, 'print'])->name('merit.print');
        Route::post('/merit-lists/{meritList}/publish', [MeritListController::class, 'publish'])->name('merit.publish');
        Route::delete('/merit-lists/{meritList}', [MeritListController::class, 'destroy'])->name('merit.destroy');

        Route::get('/cycles', [CycleController::class, 'index'])->name('cycles.index');
        Route::get('/cycles/create', [CycleController::class, 'create'])->name('cycles.create');
        Route::post('/cycles', [CycleController::class, 'store'])->name('cycles.store');
        Route::get('/cycles/{cycle}/edit', [CycleController::class, 'edit'])->name('cycles.edit');
        Route::put('/cycles/{cycle}', [CycleController::class, 'update'])->name('cycles.update');
    });
