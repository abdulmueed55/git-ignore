<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use Illuminate\Support\Facades\Route;

/*
| Public, read-only website API (v1). Consumed by the redesigned website.
| All list endpoints return only content that is live right now.
*/
Route::prefix('v1')->name('api.')->middleware('throttle:120,1,website-api')->group(function () {
    foreach (['announcements' => 'announcement', 'news' => 'news', 'events' => 'event', 'notices' => 'notice'] as $path => $type) {
        Route::get($path, [ContentController::class, 'posts'])->defaults('type', $type)->name($path);
    }
    Route::get('posts/{slug}', [ContentController::class, 'post'])->name('posts.show');
    Route::get('pages/{slug}', [ContentController::class, 'page'])->name('pages.show');
    Route::get('menu', [ContentController::class, 'menu'])->name('menu');
    Route::get('slides', [ContentController::class, 'slides'])->name('slides');
    Route::get('gallery', [ContentController::class, 'gallery'])->name('gallery');
    Route::get('gallery/{slug}', [ContentController::class, 'album'])->name('gallery.show');
    Route::get('downloads', [ContentController::class, 'downloads'])->name('downloads');
    Route::get('faculty', [ContentController::class, 'faculty'])->name('faculty');
    Route::get('settings', [ContentController::class, 'settings'])->name('settings');
    Route::get('ticker', [ContentController::class, 'ticker'])->name('ticker');
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:5,1,website-contact')->name('contact');
});
