<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('data_kantors')) {
            return;
        }

        Schema::create('data_kantors', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_dokumen', 50);
            $table->string('nama_dokumen', 255);
            $table->unsignedInteger('tahun')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('berkas');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_kantors');
    }
};
