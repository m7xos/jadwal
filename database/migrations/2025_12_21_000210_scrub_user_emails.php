<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $placeholder = 'disabled+' . $user->id . '@example.local';

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['email' => $placeholder]);
                }
            });
    }

    public function down(): void
    {
        // Tidak dapat mengembalikan email asli karena sudah disanitasi.
    }
};
