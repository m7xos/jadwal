<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_tax_settings', function (Blueprint $table): void {
            $table->string('pengurus_barang_nip', 50)->nullable()->after('pengurus_barang_no_wa');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_tax_settings', function (Blueprint $table): void {
            $table->dropColumn('pengurus_barang_nip');
        });
    }
};
