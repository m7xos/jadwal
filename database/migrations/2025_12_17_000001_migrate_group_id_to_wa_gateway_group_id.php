<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('groups', 'wa_gateway_group_id')) {
            return;
        }

        if (! Schema::hasColumn('groups', 'wablas_group_id')) {
            Schema::table('groups', function (Blueprint $table): void {
                $table->string('wa_gateway_group_id')->nullable();
            });

            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        Schema::table('groups', function (Blueprint $table) use ($driver): void {
            $column = $table->string('wa_gateway_group_id')->nullable();
            if ($driver !== 'sqlite') {
                $column->after('wablas_group_id');
            }
        });

        DB::table('groups')
            ->whereNull('wa_gateway_group_id')
            ->orWhere('wa_gateway_group_id', '=', '')
            ->update([
                'wa_gateway_group_id' => DB::raw('wablas_group_id'),
            ]);

        if ($driver !== 'sqlite') {
            Schema::table('groups', function (Blueprint $table): void {
                $table->dropColumn('wablas_group_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('groups', 'wablas_group_id')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table): void {
            $table->string('wablas_group_id')->nullable();
        });

        if (Schema::hasColumn('groups', 'wa_gateway_group_id')) {
            DB::table('groups')
                ->whereNull('wablas_group_id')
                ->orWhere('wablas_group_id', '=', '')
                ->update([
                    'wablas_group_id' => DB::raw('wa_gateway_group_id'),
                ]);
        }
    }
};
