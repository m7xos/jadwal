<?php

use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\PublicKegiatanController;
use App\Http\Controllers\KegiatanDisposisiController;
use App\Http\Controllers\KegiatanSuratController;
use App\Http\Controllers\PublicAgendaController;
use App\Http\Controllers\PublicPejabatStatusController;
use App\Http\Controllers\PublicLayananController;
use App\Http\Controllers\WaGatewayWebhookController;
use App\Http\Controllers\FilamentThemeController;
use App\Http\Controllers\YieldPanelPreferenceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\BanprovVerificationController;
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

Route::get('/status-pejabat', [PublicPejabatStatusController::class, 'index'])
    ->name('public.pejabat.status');

Route::get('/layanan/status/{kode}', [PublicLayananController::class, 'show'])
    ->name('public.layanan.status');
Route::get('/layanan/register/{kode}/print', [PublicLayananController::class, 'print'])
    ->middleware('signed')
    ->name('public.layanan.register.print');
Route::get('/layanan/daftar', [PublicLayananController::class, 'create'])
    ->name('public.layanan.register');
Route::post('/layanan/daftar', [PublicLayananController::class, 'store'])
    ->name('public.layanan.register.store');
	

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
Route::get('/banprov/verifikasi/{verification}/print', [BanprovVerificationController::class, 'print'])
    ->middleware('auth:personil')
    ->name('banprov.verifikasi.print');
Route::get('/kegiatan/disposisi/print', [KegiatanDisposisiController::class, 'bulk'])
    ->middleware('auth:personil')
    ->name('kegiatan.disposisi.bulk');
Route::get('/kegiatan/{kegiatan}/disposisi', [KegiatanDisposisiController::class, 'show'])
    ->middleware('auth:personil')
    ->name('kegiatan.disposisi');

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
