<?php

use App\Http\Controllers\Apply\ApplyController;
use App\Http\Controllers\Apply\StatusController;
use Illuminate\Support\Facades\Route;

/*
| Public applicant portal: start → documents (AI reads them) → review → submit.
| A draft is reachable only by the browser that created it (session) or after
| entering tracking no + PIN on the status page.
*/
Route::prefix('apply')->name('apply.')->group(function () {
    Route::get('/', [ApplyController::class, 'start'])->name('start');
    Route::post('/', [ApplyController::class, 'store'])->middleware('throttle:10,1,apply-start')->name('store');

    Route::get('/status', [StatusController::class, 'show'])->name('status');
    Route::post('/status', [StatusController::class, 'lookup'])->middleware('throttle:15,1,apply-lookup')->name('status.lookup');

    Route::get('/{tracking}/documents', [ApplyController::class, 'documents'])->name('documents');
    Route::post('/{tracking}/documents/{type}', [ApplyController::class, 'upload'])->middleware('throttle:60,1,apply-upload')->name('upload');
    Route::get('/{tracking}/documents/{type}/file', [ApplyController::class, 'file'])->name('file');
    Route::get('/{tracking}/progress', [ApplyController::class, 'progress'])->name('progress');
    Route::get('/{tracking}/review', [ApplyController::class, 'review'])->name('review');
    Route::post('/{tracking}/submit', [ApplyController::class, 'submit'])->middleware('throttle:10,1,apply-submit')->name('submit');
    Route::get('/{tracking}/done', [ApplyController::class, 'done'])->name('done');
});
