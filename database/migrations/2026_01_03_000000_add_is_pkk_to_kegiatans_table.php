<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatans')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            if (! Schema::hasColumn('kegiatans', 'is_pkk')) {
                $table->boolean('is_pkk')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kegiatans')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            if (Schema::hasColumn('kegiatans', 'is_pkk')) {
                $table->dropColumn('is_pkk');
            }
        });
    }
};
