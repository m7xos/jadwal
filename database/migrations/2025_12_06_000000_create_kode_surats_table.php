<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kode_surats', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->longText('keterangan')->nullable(); // <– diubah ke longText
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_surats');
    }
};
