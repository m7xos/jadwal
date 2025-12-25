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

        $columns = ['group_1_id', 'group_2_id', 'group_3_id'];
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
            $table->string('group_1_id')->nullable()->after('finish_whitelist');
            $table->string('group_2_id')->nullable()->after('group_1_id');
            $table->string('group_3_id')->nullable()->after('group_2_id');
        });
    }
};
