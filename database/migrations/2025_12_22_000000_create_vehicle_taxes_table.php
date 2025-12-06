<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_taxes', function (Blueprint $table): void {
            $table->id();
            $table->string('jenis_kendaraan', 20);
            $table->string('plat_nomor', 20)->unique();
            $table->foreignId('personil_id')
                ->nullable()
                ->constrained('personils')
                ->nullOnDelete();
            $table->date('tgl_pajak_tahunan');
            $table->date('tgl_pajak_lima_tahunan');
            $table->timestamp('last_tahunan_reminder_sent_at')->nullable();
            $table->timestamp('last_lima_tahunan_reminder_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_taxes');
    }
};
