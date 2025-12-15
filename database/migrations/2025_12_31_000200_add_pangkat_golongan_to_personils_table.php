<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personils', function (Blueprint $table): void {
            $table->string('pangkat', 100)->nullable()->after('jabatan');
            $table->string('golongan', 50)->nullable()->after('pangkat');
        });
    }

    public function down(): void
    {
        Schema::table('personils', function (Blueprint $table): void {
            $table->dropColumn(['pangkat', 'golongan']);
        });
    }
};
