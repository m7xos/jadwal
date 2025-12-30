<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_gateway_settings')) {
            return;
        }

        Schema::table('wa_gateway_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_gateway_settings', 'base_url')) {
                $table->string('base_url')->nullable()->after('api_key');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'key')) {
                $table->string('key')->nullable()->after('base_url');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'secret_key')) {
                $table->string('secret_key')->nullable()->after('key');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'provider')) {
                $table->string('provider')->default('wa-gateway')->after('secret_key');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'finish_whitelist')) {
                $table->text('finish_whitelist')->nullable()->after('provider');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'group_1_id')) {
                $table->string('group_1_id')->nullable()->after('finish_whitelist');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'group_2_id')) {
                $table->string('group_2_id')->nullable()->after('group_1_id');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'group_3_id')) {
                $table->string('group_3_id')->nullable()->after('group_2_id');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'registry_path')) {
                $table->string('registry_path')->nullable()->after('group_3_id');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'registry_url')) {
                $table->string('registry_url')->nullable()->after('registry_path');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'session_id')) {
                $table->string('session_id')->nullable()->after('registry_url');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'registry_token')) {
                $table->string('registry_token')->nullable()->after('session_id');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'registry_user')) {
                $table->string('registry_user')->nullable()->after('registry_token');
            }

            if (! Schema::hasColumn('wa_gateway_settings', 'registry_pass')) {
                $table->string('registry_pass')->nullable()->after('registry_user');
            }
        });

        $groupIds = (array) config('wa_gateway.group_ids', []);

        DB::table('wa_gateway_settings')->updateOrInsert(
            ['id' => 1],
            [
                'base_url' => config('wa_gateway.base_url'),
                'token' => config('wa_gateway.token'),
                'key' => config('wa_gateway.key'),
                'secret_key' => config('wa_gateway.secret_key'),
                'provider' => config('wa_gateway.provider', 'wa-gateway'),
                'finish_whitelist' => config('wa_gateway.finish_whitelist'),
                'group_1_id' => $groupIds['group_1'] ?? null,
                'group_2_id' => $groupIds['group_2'] ?? null,
                'group_3_id' => $groupIds['group_3'] ?? null,
                'registry_path' => config('wa_gateway.registry_path'),
                'registry_url' => config('wa_gateway.registry_url'),
                'session_id' => config('wa_gateway.session_id'),
                'registry_token' => config('wa_gateway.registry_token'),
                'registry_user' => config('wa_gateway.registry_user'),
                'registry_pass' => config('wa_gateway.registry_pass'),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('wa_gateway_settings', function (Blueprint $table) {
            $table->dropColumn([
                'base_url',
                'key',
                'secret_key',
                'provider',
                'finish_whitelist',
                'group_1_id',
                'group_2_id',
                'group_3_id',
                'registry_path',
                'registry_url',
                'session_id',
                'registry_token',
                'registry_user',
                'registry_pass',
            ]);
        });
    }
};
