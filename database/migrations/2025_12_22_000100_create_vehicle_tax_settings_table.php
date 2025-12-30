<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('vehicle_tax_settings')) {
            return;
        }

        Schema::create('vehicle_tax_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('pengurus_barang_nama')->nullable();
            $table->string('pengurus_barang_no_wa', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_tax_settings');
    }
};
