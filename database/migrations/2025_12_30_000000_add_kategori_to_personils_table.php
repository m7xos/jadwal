<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('personils') || Schema::hasColumn('personils', 'kategori')) {
            return;
        }


        Schema::table('personils', function (Blueprint $table) {
            $table->string('kategori', 50)
                ->nullable()
                ->after('jabatan')
                ->comment('Kategori personil untuk pengelompokan kirim pesan');
        });
    }

    public function down(): void
    {
        Schema::table('personils', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
