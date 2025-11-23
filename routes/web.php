<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicKegiatanController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/agenda-kegiatan', [PublicKegiatanController::class, 'index'])
    ->name('agenda.kegiatan.public');



Route::get('/agenda-kegiatan-tv', [PublicKegiatanController::class, 'tv'])
    ->name('agenda.kegiatan.tv');
	

Route::view('/pengingat-audio', 'pengingat.audio')
    ->name('pengingat.audio');

