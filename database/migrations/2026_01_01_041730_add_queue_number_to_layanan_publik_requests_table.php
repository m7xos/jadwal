<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('layanan_publik_requests') || Schema::hasColumn('layanan_publik_requests', 'queue_number')) {
            return;
        }


        Schema::table('layanan_publik_requests', function (Blueprint $table) {
            $table->unsignedInteger('queue_number')->nullable()->after('kode_register');
            $table->index(['tanggal_masuk', 'queue_number'], 'layanan_publik_requests_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('layanan_publik_requests', function (Blueprint $table) {
            $table->dropIndex('layanan_publik_requests_queue_index');
            $table->dropColumn('queue_number');
        });
    }
};
