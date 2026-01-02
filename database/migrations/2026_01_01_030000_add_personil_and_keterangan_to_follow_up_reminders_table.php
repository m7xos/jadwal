<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('follow_up_reminders')) {
            return;
        }

        Schema::table('follow_up_reminders', function (Blueprint $table) {
            if (! Schema::hasColumn('follow_up_reminders', 'personil_id')) {
                $table->foreignId('personil_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('personils')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('follow_up_reminders', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('tempat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('personil_id');
            $table->dropColumn('keterangan');
        });
    }
};
