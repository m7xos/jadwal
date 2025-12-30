<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat_keluar_counters')) {
            return;
        }

        Schema::create('surat_keluar_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kode_surat_id')->constrained('kode_surats')->cascadeOnDelete();
            $table->unsignedInteger('tahun');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['kode_surat_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keluar_counters');
    }
};
