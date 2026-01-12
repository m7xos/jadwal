<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('groups') || Schema::hasColumn('groups', 'agenda_scope')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table): void {
            $table->string('agenda_scope')
                ->default('default')
                ->after('is_default');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('groups') || ! Schema::hasColumn('groups', 'agenda_scope')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table): void {
            $table->dropColumn('agenda_scope');
        });
    }
};
