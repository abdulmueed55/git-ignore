<?php

use App\Http\Controllers\Studio\DashboardController;
use App\Http\Controllers\Studio\DownloadController;
use App\Http\Controllers\Studio\FacultyController;
use App\Http\Controllers\Studio\GalleryController;
use App\Http\Controllers\Studio\MessageController;
use App\Http\Controllers\Studio\PageController;
use App\Http\Controllers\Studio\PostController;
use App\Http\Controllers\Studio\SettingsController;
use App\Http\Controllers\Studio\SlideController;
use Illuminate\Support\Facades\Route;

/*
| Website studio — the CMS behind the public college website.
*/
Route::middleware(['auth', 'role:principal,editor'])->prefix('studio')->name('studio.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::post('posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
    Route::post('posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('posts.duplicate');
    Route::patch('posts/{post}/pin', [PostController::class, 'togglePin'])->name('posts.pin');
    Route::resource('posts', PostController::class)->except('show');

    Route::resource('pages', PageController::class)->except('show');

    Route::post('slides/reorder', [SlideController::class, 'reorder'])->name('slides.reorder');
    Route::resource('slides', SlideController::class)->except('show');

    Route::post('gallery/{gallery}/images', [GalleryController::class, 'upload'])->name('gallery.images.store');
    Route::delete('gallery/images/{image}', [GalleryController::class, 'destroyImage'])->name('gallery.images.destroy');
    Route::resource('gallery', GalleryController::class)->parameters(['gallery' => 'gallery']);

    Route::resource('downloads', DownloadController::class)->except('show');

    Route::post('faculty/import', [FacultyController::class, 'import'])->name('faculty.import');
    Route::resource('faculty', FacultyController::class)->except('show')->parameters(['faculty' => 'faculty']);

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
    Route::patch('messages/{message}/unread', [MessageController::class, 'unread'])->name('messages.unread');
    Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
