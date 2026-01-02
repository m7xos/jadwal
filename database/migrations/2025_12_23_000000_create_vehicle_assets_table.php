<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_assets')) {
            return;
        }

        Schema::create('vehicle_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('id_pemda')->nullable();
            $table->string('kode_upb')->nullable();
            $table->string('nama_upb')->nullable();
            $table->string('kode_aset')->nullable();
            $table->string('nama_aset')->nullable();
            $table->string('reg')->nullable();
            $table->string('merk_type')->nullable();
            $table->string('ukuran_cc')->nullable();
            $table->string('bahan')->nullable();
            $table->date('tahun')->nullable();
            $table->string('nomor_pabrik')->nullable();
            $table->string('nomor_rangka')->nullable();
            $table->string('nomor_mesin')->nullable();
            $table->string('nomor_polisi')->nullable();
            $table->string('nomor_bpkb')->nullable();
            $table->decimal('harga', 18, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assets');
    }
};
