<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_access_settings', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique();
            $table->json('allowed_pages')->nullable();
            $table->timestamps();
        });

        // Siapkan entri awal untuk tiap peran.
        $now = now();
        $defaults = [
            [
                'role' => UserRole::Admin->value,
                'allowed_pages' => json_encode(['*']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'role' => UserRole::Arsiparis->value,
                'allowed_pages' => json_encode([
                    'filament.admin.pages.dashboard',
                    'filament.admin.pages.profile',
                    'filament.admin.resources.kegiatans',
                    'filament.admin.resources.personils',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'role' => UserRole::Pengguna->value,
                'allowed_pages' => json_encode([
                    'filament.admin.pages.dashboard',
                    'filament.admin.pages.profile',
                    'filament.admin.resources.kegiatans',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('role_access_settings')->insert($defaults);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_access_settings');
    }
};
