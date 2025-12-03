<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE kegiatans MODIFY waktu VARCHAR(255) NULL');
        DB::statement('ALTER TABLE kegiatans MODIFY tempat VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kegiatans MODIFY waktu VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE kegiatans MODIFY tempat VARCHAR(255) NOT NULL');
    }
};
