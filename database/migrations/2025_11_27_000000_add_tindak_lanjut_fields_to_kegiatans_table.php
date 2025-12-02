<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            $table->string('jenis_surat')->default('undangan')->after('id');
            $table->dateTime('batas_tindak_lanjut')->nullable()->after('keterangan');
            $table->timestamp('tl_reminder_sent_at')->nullable()->after('batas_tindak_lanjut');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            $table->dropColumn(['jenis_surat', 'batas_tindak_lanjut', 'tl_reminder_sent_at']);
        });
    }
};
