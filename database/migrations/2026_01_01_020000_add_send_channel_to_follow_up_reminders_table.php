<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_up_reminders', function (Blueprint $table) {
            if (! Schema::hasColumn('follow_up_reminders', 'send_via')) {
                $table->string('send_via', 20)
                    ->default('personal')
                    ->after('normalized_no_wa');
            }

            if (! Schema::hasColumn('follow_up_reminders', 'group_id')) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('send_via')
                    ->constrained('groups')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn('send_via');
        });
    }
};
