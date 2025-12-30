<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_tax_reminder_logs')) {
            return;
        }

        Schema::create('vehicle_tax_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_tax_id')
                ->constrained('vehicle_taxes')
                ->cascadeOnDelete();
            $table->string('type', 20); // tahunan / lima_tahunan
            $table->string('stage', 10)->nullable(); // H-7 / H-3 / H0
            $table->string('status', 20)->default('pending'); // pending/success/failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tax_reminder_logs');
    }
};
