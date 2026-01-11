<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('surat_keputusans', function (Blueprint $table) {
            if (! Schema::hasColumn('surat_keputusans', 'berkas_surat')) {
                $table->string('berkas_surat')->nullable()->after('perihal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_keputusans', function (Blueprint $table) {
            if (Schema::hasColumn('surat_keputusans', 'berkas_surat')) {
                $table->dropColumn('berkas_surat');
            }
        });
    }
};
