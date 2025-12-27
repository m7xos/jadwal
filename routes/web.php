<?php

use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\PublicKegiatanController;
use App\Http\Controllers\KegiatanSuratController;
use App\Http\Controllers\PublicAgendaController;
use App\Http\Controllers\PublicLayananController;
use App\Http\Controllers\WaGatewayWebhookController;
use App\Http\Controllers\FilamentThemeController;
use App\Http\Controllers\YieldPanelPreferenceController;
use App\Http\Controllers\ScheduleController;
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

Route::get('/layanan/status/{kode}', [PublicLayananController::class, 'show'])
    ->name('public.layanan.status');
Route::get('/layanan/register/{kode}/print', [PublicLayananController::class, 'print'])
    ->middleware('signed')
    ->name('public.layanan.register.print');
	

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

// Form sederhana untuk input jadwal (dev/internal)
Route::get('/webhook/schedules/new', [ScheduleController::class, 'create'])
    ->name('webhook.schedules.create');
Route::post('/webhook/schedules', [ScheduleController::class, 'store'])
    ->name('webhook.schedules.store');

Route::post('/admin/theme', [FilamentThemeController::class, 'update'])
    ->middleware('auth:personil')
    ->name('filament.theme');

Route::post('/admin/yield-panel', [YieldPanelPreferenceController::class, 'update'])
    ->middleware('auth:personil')
    ->name('yieldpanel.pref');
