<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_taxes')) {
            return;
        }

        Schema::table('vehicle_taxes', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_taxes', 'status_pajak')) {
                $table->string('status_pajak', 20)->default('pending')->after('last_lima_tahunan_reminder_for_date');
            }

            if (! Schema::hasColumn('vehicle_taxes', 'pajak_lunas_at')) {
                $table->timestamp('pajak_lunas_at')->nullable()->after('status_pajak');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_taxes', function (Blueprint $table): void {
            $table->dropColumn(['status_pajak', 'pajak_lunas_at']);
        });
    }
};
