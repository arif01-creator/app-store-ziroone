<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
|--------------------------------------------------------------------------
| Public client-facing routes (no auth)
|--------------------------------------------------------------------------
|
| The only pages a client ever sees. Declared before the authenticated
| resource routes purely for readability — the URI segments do not collide.
|
*/

Route::get('apps/{app:slug}/download', [PublicDownloadController::class, 'show'])
    ->name('public.apps.show');

Route::post('apps/{app:slug}/download', [PublicDownloadController::class, 'download'])
    ->name('public.apps.download');

/*
|--------------------------------------------------------------------------
| Admin panel (authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('apps', AppController::class)->except(['destroy']);

    Route::patch('apps/{app}/devices/{device}', [AppController::class, 'updateDevice'])
        ->name('apps.devices.update');

    Route::post('apps/{app}/versions', [AppVersionController::class, 'store'])
        ->name('apps.versions.store');

    Route::patch('apps/{app}/versions/{version}', [AppVersionController::class, 'update'])
        ->name('apps.versions.update');

    Route::get('apps/{app}/versions/{version}/download', [AppVersionController::class, 'download'])
        ->name('apps.versions.download');
});

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
