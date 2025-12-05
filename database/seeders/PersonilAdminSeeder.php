<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Personil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PersonilAdminSeeder extends Seeder
{
    public function run(): void
    {
        $nip = env('DEFAULT_PERSONIL_NIP', '0000000000000000');
        $phone = env('DEFAULT_PERSONIL_PHONE', '6281111111111');
        $name = env('DEFAULT_PERSONIL_NAME', 'Administrator');

        Personil::updateOrCreate(
            ['nip' => $nip],
            [
                'nama' => $name,
                'jabatan' => 'Administrator',
                'no_wa' => $phone,
                'password' => Hash::make($phone),
                'role' => UserRole::Admin,
            ],
        );
    }
}
