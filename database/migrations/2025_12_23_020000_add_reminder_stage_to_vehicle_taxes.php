<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_taxes', function (Blueprint $table): void {
            $table->string('last_tahunan_reminder_stage', 10)->nullable()->after('last_tahunan_reminder_sent_at');
            $table->date('last_tahunan_reminder_for_date')->nullable()->after('last_tahunan_reminder_stage');
            $table->string('last_lima_tahunan_reminder_stage', 10)->nullable()->after('last_lima_tahunan_reminder_sent_at');
            $table->date('last_lima_tahunan_reminder_for_date')->nullable()->after('last_lima_tahunan_reminder_stage');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_taxes', function (Blueprint $table): void {
            $table->dropColumn([
                'last_tahunan_reminder_stage',
                'last_tahunan_reminder_for_date',
                'last_lima_tahunan_reminder_stage',
                'last_lima_tahunan_reminder_for_date',
            ]);
        });
    }
};
