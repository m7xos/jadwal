<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Bisa pakai env supaya password tidak hardcode di repo
        $email = env('DEFAULT_ADMIN_EMAIL', 'disabled@example.local');
        $password = env('DEFAULT_ADMIN_PASSWORD', 'password123');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => 'Admin Sistem',
                'password' => Hash::make($password),
                'role'     => UserRole::Admin,
            ],
        );
    }
}
