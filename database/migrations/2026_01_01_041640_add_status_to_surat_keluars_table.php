<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('surat_keluars')) {
            return;
        }

        Schema::table('surat_keluars', function (Blueprint $table) {
            if (! Schema::hasColumn('surat_keluars', 'status')) {
                $table->string('status', 20)->default('issued')->after('perihal');
            }

            if (! Schema::hasColumn('surat_keluars', 'booked_at')) {
                $table->date('booked_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_keluars', function (Blueprint $table) {
            $table->dropColumn(['status', 'booked_at']);
        });
    }
};
