<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_tax_settings') || Schema::hasColumn('vehicle_tax_settings', 'personil_id')) {
            return;
        }


        Schema::table('vehicle_tax_settings', function (Blueprint $table): void {
            $table->foreignId('personil_id')
                ->nullable()
                ->after('id')
                ->constrained('personils')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_tax_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('personil_id');
        });
    }
};
