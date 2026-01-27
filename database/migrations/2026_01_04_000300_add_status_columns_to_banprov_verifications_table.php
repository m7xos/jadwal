<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banprov_verifications', function (Blueprint $table): void {
            $table->boolean('status_lpj')->default(false)->after('jumlah');
            $table->boolean('status_monev')->default(false)->after('status_lpj');
        });
    }

    public function down(): void
    {
        Schema::table('banprov_verifications', function (Blueprint $table): void {
            $table->dropColumn(['status_lpj', 'status_monev']);
        });
    }
};
