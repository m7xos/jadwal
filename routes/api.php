<?php

use App\Http\Controllers\WablasWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/wablas/webhook', WablasWebhookController::class)
    ->name('wablas.webhook');
