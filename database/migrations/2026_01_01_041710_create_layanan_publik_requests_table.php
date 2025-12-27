<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layanan_publik_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layanan_publik_id')->constrained('layanan_publiks')->cascadeOnDelete();
            $table->string('kode_register')->unique();
            $table->string('nama_pemohon');
            $table->string('no_wa_pemohon', 30)->nullable();
            $table->string('status', 30)->default('registered');
            $table->date('tanggal_masuk');
            $table->date('tanggal_selesai')->nullable();
            $table->string('perangkat_desa_nama')->nullable();
            $table->string('perangkat_desa_wa', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layanan_publik_requests');
    }
};
