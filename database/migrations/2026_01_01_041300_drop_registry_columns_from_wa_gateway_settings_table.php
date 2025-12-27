<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_gateway_settings')) {
            return;
        }

        $columns = ['registry_path', 'registry_url', 'session_id'];
        $existing = array_filter($columns, fn ($column) => Schema::hasColumn('wa_gateway_settings', $column));

        if ($existing) {
            Schema::table('wa_gateway_settings', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_gateway_settings')) {
            return;
        }

        Schema::table('wa_gateway_settings', function (Blueprint $table) {
            $table->string('registry_path')->nullable()->after('finish_whitelist');
            $table->string('registry_url')->nullable()->after('registry_path');
            $table->string('session_id')->nullable()->after('registry_url');
        });
    }
};
