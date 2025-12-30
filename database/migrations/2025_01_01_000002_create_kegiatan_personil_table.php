<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kegiatan_personil')) {
            return;
        }

        Schema::create('kegiatan_personil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')
                ->constrained('kegiatans')
                ->cascadeOnDelete();
            $table->foreignId('personil_id')
                ->constrained('personils')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kegiatan_id', 'personil_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_personil');
    }
};
