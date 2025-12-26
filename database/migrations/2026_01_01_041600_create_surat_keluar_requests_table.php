<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keluar_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requester_number', 30)->index();
            $table->foreignId('requester_personil_id')->nullable()->constrained('personils')->nullOnDelete();
            $table->string('group_id', 120)->nullable();
            $table->string('status', 40)->default('waiting_klasifikasi');
            $table->foreignId('kode_surat_id')->nullable()->constrained('kode_surats')->nullOnDelete();
            $table->text('perihal')->nullable();
            $table->string('source', 20)->default('wa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keluar_requests');
    }
};
