<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            $table->string('jenis_surat')
                ->default('undangan')
                ->after('surat_undangan');

            $table->boolean('tampilkan_di_public')
                ->default(true)
                ->after('sudah_disposisi');

            $table->dateTime('tindak_lanjut_deadline')
                ->nullable()
                ->after('tampilkan_di_public');

            $table->dateTime('tindak_lanjut_reminder_sent_at')
                ->nullable()
                ->after('tindak_lanjut_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            $table->dropColumn([
                'jenis_surat',
                'tampilkan_di_public',
                'tindak_lanjut_deadline',
                'tindak_lanjut_reminder_sent_at',
            ]);
        });
    }
};
