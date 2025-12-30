<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('layanan_publik_status_logs')) {
            return;
        }

        Schema::create('layanan_publik_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layanan_publik_request_id')
                ->constrained('layanan_publik_requests')
                ->cascadeOnDelete();
            $table->string('status', 30);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by_personil_id')->nullable()
                ->constrained('personils')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layanan_publik_status_logs');
    }
};
