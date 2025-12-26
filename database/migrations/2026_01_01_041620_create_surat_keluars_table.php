<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keluars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kode_surat_id')->constrained('kode_surats')->cascadeOnDelete();
            $table->unsignedInteger('tahun');
            $table->unsignedInteger('nomor_urut');
            $table->unsignedInteger('nomor_sisipan')->default(0);
            $table->foreignId('master_id')->nullable()->constrained('surat_keluars')->nullOnDelete();
            $table->text('perihal');
            $table->string('requested_by_number', 30)->nullable();
            $table->foreignId('requested_by_personil_id')->nullable()->constrained('personils')->nullOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('surat_keluar_requests')->nullOnDelete();
            $table->string('source', 20)->default('wa');
            $table->timestamps();

            $table->unique(['kode_surat_id', 'tahun', 'nomor_urut', 'nomor_sisipan'], 'surat_keluars_unique_nomor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keluars');
    }
};
