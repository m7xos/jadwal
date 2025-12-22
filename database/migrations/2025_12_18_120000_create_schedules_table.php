<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('id_group', 191)->index();
            $table->string('title', 255);
            $table->dateTime('starts_at')->index();
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_disposed')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
