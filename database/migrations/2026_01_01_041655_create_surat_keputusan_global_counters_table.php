<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat_keputusan_global_counters')) {
            return;
        }

        Schema::create('surat_keputusan_global_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tahun')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keputusan_global_counters');
    }
};
