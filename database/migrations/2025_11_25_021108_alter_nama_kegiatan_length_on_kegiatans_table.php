<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah kolom jadi VARCHAR(500) (atau sesuaikan)
        DB::statement('ALTER TABLE kegiatans MODIFY nama_kegiatan VARCHAR(500) NOT NULL');
    }

    public function down(): void
    {
        // Kembalikan ke VARCHAR(255) kalau di-rollback
        DB::statement('ALTER TABLE kegiatans MODIFY nama_kegiatan VARCHAR(255) NOT NULL');
    }
};
