<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_personil', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personil_id')->constrained('personils')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'personil_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_personil');
    }
};
