<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_kegiatan')) {
            return;
        }

        Schema::create('group_kegiatan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->constrained('kegiatans')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'kegiatan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_kegiatan');
    }
};
