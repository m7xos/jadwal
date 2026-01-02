<?php

use App\Http\Controllers\WaGatewayWebhookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\KegiatanController;
use App\Http\Controllers\Api\LayananController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WaInboxController;
use Illuminate\Support\Facades\Route;

Route::post('/wa-gateway/webhook/message', WaGatewayWebhookController::class)
    ->name('wa-gateway.webhook.message');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/layanan/status/{kode}', [LayananController::class, 'status']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/layanan', [LayananController::class, 'index']);
        Route::post('/layanan/register', [LayananController::class, 'store']);

        Route::get('/kegiatan', [KegiatanController::class, 'index']);
        Route::get('/kegiatan/{kegiatan}', [KegiatanController::class, 'show']);
        Route::patch('/kegiatan/{kegiatan}/disposisi', [KegiatanController::class, 'updateDisposisi']);

        Route::get('/wa-inbox', [WaInboxController::class, 'index']);
        Route::post('/wa-inbox/{message}/reply', [WaInboxController::class, 'reply']);
    });
});
