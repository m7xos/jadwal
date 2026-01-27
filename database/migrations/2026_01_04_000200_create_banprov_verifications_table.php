<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('banprov_verifications')) {
            return;
        }

        Schema::create('banprov_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('tahap', 20);
            $table->string('kecamatan', 100);
            $table->string('desa', 150)->nullable();
            $table->string('no_dpa', 100)->nullable();
            $table->text('jenis_kegiatan')->nullable();
            $table->bigInteger('jumlah')->nullable();
            $table->string('sumber_file')->nullable();
            $table->timestamps();

            $table->index(['tahap', 'kecamatan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banprov_verifications');
    }
};
