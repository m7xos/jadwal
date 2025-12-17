<?php

use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\PublicKegiatanController;
use App\Http\Controllers\KegiatanSuratController;
use App\Http\Controllers\PublicAgendaController;
use App\Http\Controllers\WaGatewayWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

// routes/web.php
Route::get('/health', fn () => response()->json(['status' => 'ok']));

//Route::get('/', function () {
//    return view('audio');
//});
// Halaman depan: daftar aplikasi
Route::view('/', 'home.menu')->name('home');

//Route::get('/agenda-kegiatan', [PublicKegiatanController::class, 'index'])
//    ->name('agenda.kegiatan.public');

Route::get('/agenda-kegiatan', [PublicAgendaController::class, 'index'])
    ->name('public.agenda.index');

// Halaman tampilan TV (yang full-screen tadi)
Route::get('/agenda-kegiatan-tv', [PublicAgendaController::class, 'tv'])
    ->name('public.agenda.tv');
	

Route::view('/pengingat-audio', 'pengingat.audio')
    ->name('pengingat.audio');
	
Route::get('/u/{kegiatan}', [KegiatanSuratController::class, 'show'])
    ->name('kegiatan.surat.short');

Route::get('/preview-surat/{token}', [KegiatanSuratController::class, 'preview'])
    ->middleware('signed')
    ->name('kegiatan.surat.preview');

Route::get('/kegiatan/{kegiatan}/surat-tugas', [KegiatanSuratController::class, 'suratTugas'])
    ->middleware('auth:personil')
    ->name('kegiatan.surat_tugas');

Route::get('/kegiatan/{kegiatan}/sppd', [KegiatanSuratController::class, 'sppd'])
    ->middleware('auth:personil')
    ->name('kegiatan.sppd');

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

// Webhook wa-gateway: set webhookBaseUrl ke {APP_URL}/wa-gateway/webhook (gateway akan POST ke /message)
Route::post('/wa-gateway/webhook/message', WaGatewayWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('wa-gateway.webhook.message.web');
