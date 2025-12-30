<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'no_wa')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('no_wa', 30)
                ->nullable()
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('no_wa');
        });
    }
};
