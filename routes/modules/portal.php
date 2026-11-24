<?php

use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Public college website (approved design). Content comes from the Website Studio.
*/
Route::get('/', [PortalController::class, 'home'])->name('portal.home');
Route::get('/about', [PortalController::class, 'about'])->name('site.about');
Route::get('/admission', [PortalController::class, 'admission'])->name('site.admission');
Route::get('/programmes', [PortalController::class, 'programmes'])->name('site.programmes');
Route::get('/faculty', [PortalController::class, 'faculty'])->name('site.faculty');
Route::get('/campus-life', [PortalController::class, 'campus'])->name('site.campus');
Route::get('/notices', [PortalController::class, 'notices'])->name('site.notices');
Route::get('/contact', [PortalController::class, 'contact'])->name('site.contact');
Route::post('/contact', [PortalController::class, 'sendMessage'])->middleware('throttle:website-contact')->name('site.contact.store');

Route::get('/notices/{slug}', [PortalController::class, 'post'])->name('portal.post');
Route::get('/pages/{slug}', [PortalController::class, 'page'])->name('portal.page');
Route::get('/downloads/{download}', [PortalController::class, 'download'])->name('portal.download');
