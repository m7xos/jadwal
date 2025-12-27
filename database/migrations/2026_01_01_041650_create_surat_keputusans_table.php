<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keputusans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kode_surat_id')->constrained('kode_surats')->cascadeOnDelete();
            $table->unsignedInteger('tahun');
            $table->unsignedInteger('nomor_urut');
            $table->unsignedInteger('nomor_sisipan')->default(0);
            $table->foreignId('master_id')->nullable()->constrained('surat_keputusans')->nullOnDelete();
            $table->date('tanggal_surat')->nullable();
            $table->date('tanggal_diundangkan')->nullable();
            $table->text('perihal');
            $table->string('status', 20)->default('issued');
            $table->date('booked_at')->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->unique(['tahun', 'nomor_urut', 'nomor_sisipan'], 'surat_keputusans_unique_nomor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keputusans');
    }
};
