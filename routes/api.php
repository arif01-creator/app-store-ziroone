<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\VersionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API (consumed by the Flutter clients)
|--------------------------------------------------------------------------
|
| Prefixed with /api/v1 (see bootstrap/app.php). Scoped entirely by the
| per-app api_key — there is no session, cookie, or user auth here.
|
*/

Route::prefix('apps/{api_key}')->group(function () {
    Route::post('register', [DeviceController::class, 'register'])
        ->name('api.devices.register');

    Route::get('latest', [VersionController::class, 'latest'])
        ->name('api.versions.latest');

    Route::get('download/{version_code}', [VersionController::class, 'download'])
        ->whereNumber('version_code')
        ->name('api.versions.download');
});
