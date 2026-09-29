<?php

use App\Http\Controllers\Device\CommandController;
use App\Http\Controllers\Etaotp\QueueController;
use App\Http\Controllers\Device\HeartbeatController;
use App\Http\Controllers\Device\NameController;
use App\Http\Controllers\Device\QrCodeController;
use App\Http\Controllers\Device\RegisterController;
use App\Http\Controllers\Device\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Tahta sunucuya dışarıdan bağlanır. Tahtaya kapı açılmaz.
| Kayıt anahtarı pakette durur; sonraki istekler device_token taşır.
|
| POST /api/device/register
| POST /api/device/heartbeat
| POST /api/device/name
| POST /api/device/qr
| GET  /api/device/settings
| GET  /api/device/commands?wait=20
| POST /api/device/commands/{id}/ack
*/

Route::prefix('etaotp')->middleware(['etaotp', 'throttle:60,1'])->group(function () {
    Route::post('claim', [QueueController::class, 'claim']);
    Route::post('{id}/sent', [QueueController::class, 'sent'])->whereNumber('id');
    Route::post('{id}/failed', [QueueController::class, 'failed'])->whereNumber('id');
    Route::post('{id}/release', [QueueController::class, 'release'])->whereNumber('id');
});

Route::prefix('device')->group(function () {
    Route::post('register', RegisterController::class)
        ->middleware('throttle:device-register');

    Route::middleware(['device', 'throttle:device'])->group(function () {
        Route::post('heartbeat', HeartbeatController::class);
        Route::get('settings', SettingsController::class);
        Route::post('name', NameController::class);
        Route::post('qr', QrCodeController::class);
        Route::get('commands', [CommandController::class, 'index']);
        Route::post('commands/{command}/ack', [CommandController::class, 'ack']);
    });
});
