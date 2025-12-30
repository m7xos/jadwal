<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personil_device_tokens')) {
            return;
        }

        Schema::create('personil_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personil_id')->constrained('personils')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform')->default('android');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personil_device_tokens');
    }
};
