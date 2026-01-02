<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatans') || Schema::hasColumn('kegiatans', 'sudah_disposisi')) {
            return;
        }


        Schema::table('kegiatans', function (Blueprint $table) {
            $table->boolean('sudah_disposisi')
                ->default(false)
                ->after('surat_undangan');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatans', function (Blueprint $table) {
            $table->dropColumn('sudah_disposisi');
        });
    }
};
