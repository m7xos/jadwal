<?php

use App\Http\Controllers\WaGatewayWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/wa-gateway/webhook/message', WaGatewayWebhookController::class)
    ->name('wa-gateway.webhook.message');
