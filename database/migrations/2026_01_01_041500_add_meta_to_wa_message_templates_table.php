<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_message_templates') || Schema::hasColumn('wa_message_templates', 'meta')) {
            return;
        }


        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_templates', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
