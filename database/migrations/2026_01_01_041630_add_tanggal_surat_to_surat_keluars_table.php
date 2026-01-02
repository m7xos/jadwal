<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('surat_keluars') || Schema::hasColumn('surat_keluars', 'tanggal_surat')) {
            return;
        }


        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->date('tanggal_surat')->nullable()->after('nomor_sisipan');
        });
    }

    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn('tanggal_surat');
        });
    }
};
