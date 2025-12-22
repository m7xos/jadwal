<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatan_personil_category')) {
            Schema::create('kegiatan_personil_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kegiatan_id')->constrained('kegiatans')->cascadeOnDelete();
                $table->foreignId('personil_category_id')->constrained('personil_categories')->cascadeOnDelete();
                $table->timestamps();
                // pakai nama unik pendek agar tidak melebihi batas MySQL
                $table->unique(['kegiatan_id', 'personil_category_id'], 'kegiatan_pcat_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_personil_category');
    }
};
