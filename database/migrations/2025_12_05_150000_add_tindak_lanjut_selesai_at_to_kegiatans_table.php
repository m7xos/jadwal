<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            if (! Schema::hasColumn('kegiatans', 'tindak_lanjut_selesai_at')) {
                $table->dateTime('tindak_lanjut_selesai_at')
                    ->nullable()
                    ->after('tl_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            if (Schema::hasColumn('kegiatans', 'tindak_lanjut_selesai_at')) {
                $table->dropColumn('tindak_lanjut_selesai_at');
            }
        });
    }
};
