<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatans') || Schema::hasColumn('kegiatans', 'tanggal_surat')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            $table->date('tanggal_surat')->nullable()->after('nomor');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kegiatans') || ! Schema::hasColumn('kegiatans', 'tanggal_surat')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            $table->dropColumn('tanggal_surat');
        });
    }
};
