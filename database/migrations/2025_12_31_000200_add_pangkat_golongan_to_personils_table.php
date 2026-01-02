<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personils')) {
            return;
        }

        Schema::table('personils', function (Blueprint $table): void {
            if (! Schema::hasColumn('personils', 'pangkat')) {
                $table->string('pangkat', 100)->nullable()->after('jabatan');
            }

            if (! Schema::hasColumn('personils', 'golongan')) {
                $table->string('golongan', 50)->nullable()->after('pangkat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personils', function (Blueprint $table): void {
            $table->dropColumn(['pangkat', 'golongan']);
        });
    }
};
