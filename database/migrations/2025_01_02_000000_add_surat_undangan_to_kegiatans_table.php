<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatans') || Schema::hasColumn('kegiatans', 'surat_undangan')) {
            return;
        }


        Schema::table('kegiatans', function (Blueprint $table) {
            $table->string('surat_undangan')->nullable()->after('nomor');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            $table->dropColumn('surat_undangan');
        });
    }
};
