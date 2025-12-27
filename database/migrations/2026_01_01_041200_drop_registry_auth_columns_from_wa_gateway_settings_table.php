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

        $columns = ['registry_token', 'registry_user', 'registry_pass'];
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
            $table->string('registry_token')->nullable()->after('session_id');
            $table->string('registry_user')->nullable()->after('registry_token');
            $table->string('registry_pass')->nullable()->after('registry_user');
        });
    }
};
