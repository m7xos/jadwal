<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tindak_lanjut_reminder_logs') || Schema::hasColumn('tindak_lanjut_reminder_logs', 'type')) {
            return;
        }


        Schema::table('tindak_lanjut_reminder_logs', function (Blueprint $table): void {
            $table->string('type')->default('awal')->after('kegiatan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tindak_lanjut_reminder_logs', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
