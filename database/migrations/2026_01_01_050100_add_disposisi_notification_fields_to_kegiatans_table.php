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
            if (! Schema::hasColumn('kegiatans', 'disposisi_notified_at')) {
                $table->timestamp('disposisi_notified_at')->nullable()->after('sudah_disposisi');
            }

            if (! Schema::hasColumn('kegiatans', 'disposisi_escalated_at')) {
                $table->timestamp('disposisi_escalated_at')->nullable()->after('disposisi_notified_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kegiatans')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            if (Schema::hasColumn('kegiatans', 'disposisi_escalated_at')) {
                $table->dropColumn('disposisi_escalated_at');
            }

            if (Schema::hasColumn('kegiatans', 'disposisi_notified_at')) {
                $table->dropColumn('disposisi_notified_at');
            }
        });
    }
};
