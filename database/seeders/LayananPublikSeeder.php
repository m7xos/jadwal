<?php

namespace Database\Seeders;

use App\Models\LayananPublik;
use Illuminate\Database\Seeder;

class LayananPublikSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'nama' => 'Adminduk KTP',
                'kategori' => 'Adminduk - Offline (Kantor Kecamatan)',
                'deskripsi' => 'Pengurusan KTP secara langsung di kantor kecamatan.',
            ],
            [
                'nama' => 'Adminduk KK',
                'kategori' => 'Adminduk - Offline (Kantor Kecamatan)',
                'deskripsi' => 'Pengurusan KK secara langsung di kantor kecamatan.',
            ],
            [
                'nama' => 'Adminduk KTP',
                'kategori' => 'Adminduk - Online (Pandawa)',
                'deskripsi' => 'Pengurusan KTP melalui aplikasi Pandawa.',
            ],
            [
                'nama' => 'Adminduk KK',
                'kategori' => 'Adminduk - Online (Pandawa)',
                'deskripsi' => 'Pengurusan KK melalui aplikasi Pandawa.',
            ],
        ];

        foreach ($items as $item) {
            LayananPublik::updateOrCreate(
                [
                    'nama' => $item['nama'],
                    'kategori' => $item['kategori'],
                ],
                [
                    'deskripsi' => $item['deskripsi'],
                    'aktif' => true,
                ]
            );
        }
    }
}
