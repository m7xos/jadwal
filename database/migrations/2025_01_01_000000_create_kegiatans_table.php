<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kegiatans')) {
            return;
        }

        Schema::create('kegiatans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->string('nama_kegiatan');
            $table->date('tanggal');           // hari / tanggal
            $table->string('waktu');           // contoh: 09.00 - 11.00 WIB
            $table->string('tempat');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatans');
    }
};
