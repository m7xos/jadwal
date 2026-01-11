<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personils') || Schema::hasColumn('personils', 'photo_url')) {
            return;
        }

        Schema::table('personils', function (Blueprint $table): void {
            $table->string('photo_url')->nullable()->after('nip');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('personils') || ! Schema::hasColumn('personils', 'photo_url')) {
            return;
        }

        Schema::table('personils', function (Blueprint $table): void {
            $table->dropColumn('photo_url');
        });
    }
};
