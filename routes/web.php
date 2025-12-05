<?php

use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\PublicKegiatanController;
use App\Http\Controllers\KegiatanSuratController;
use App\Http\Controllers\PublicAgendaController;
use App\Http\Controllers\WablasWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

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

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

// Webhook Wablas (tanpa CSRF) agar bisa dipanggil dari panel Wablas dengan URL /wablas/webhook
Route::post('/wablas/webhook', WablasWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('wablas.webhook.web');
