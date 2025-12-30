<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personils') || Schema::hasColumn('personils', 'nip')) {
            return;
        }


        Schema::table('personils', function (Blueprint $table) {
            // NIP max 30 karakter, boleh NULL
            $table->string('nip', 30)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('personils', function (Blueprint $table) {
            $table->dropColumn('nip');
        });
    }
};
