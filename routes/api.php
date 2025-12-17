<?php

use App\Http\Controllers\WablasWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/wablas/webhook', WablasWebhookController::class)
    ->name('wablas.webhook');

Route::post('/wa-gateway/webhook/message', WablasWebhookController::class)
    ->name('wa-gateway.webhook.message');
