<?php

use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Temporary public landing page — reads everything from the Website studio.
| Will be replaced by the redesigned website (which can use /api/v1 instead).
*/
Route::get('/', [PortalController::class, 'home'])->name('portal.home');
Route::get('/notices/{slug}', [PortalController::class, 'post'])->name('portal.post');
Route::get('/pages/{slug}', [PortalController::class, 'page'])->name('portal.page');
Route::get('/downloads/{download}', [PortalController::class, 'download'])->name('portal.download');
