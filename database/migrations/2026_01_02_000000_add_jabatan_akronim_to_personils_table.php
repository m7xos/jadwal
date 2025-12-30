<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personils') || Schema::hasColumn('personils', 'jabatan_akronim')) {
            return;
        }


        Schema::table('personils', function (Blueprint $table): void {
            $table->string('jabatan_akronim', 50)->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('personils', function (Blueprint $table): void {
            $table->dropColumn('jabatan_akronim');
        });
    }
};
