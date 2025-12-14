<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sesuaikan dengan struktur kelompok di kantor kamu
        $groups = [
            [
                'nama'            => 'Grup 1',
                'wablas_group_id' => env('WABLAS_GROUP_1_ID', ''), // ID grup dari Solo Wablas
                'is_default'      => true,
                'keterangan'      => 'Grup WhatsApp kantor 1',
            ],
            [
                'nama'            => 'Grup 2',
                'wablas_group_id' => env('WABLAS_GROUP_2_ID', ''),
                'keterangan'      => 'Grup WhatsApp kantor 2',
            ],
            [
                'nama'            => 'Grup 3',
                'wablas_group_id' => env('WABLAS_GROUP_3_ID', ''),
                'keterangan'      => 'Grup WhatsApp kantor 3',
            ],
        ];

        foreach ($groups as $data) {
            Group::updateOrCreate(
                ['nama' => $data['nama']], // kunci unik berdasarkan nama grup
                [
                    'wablas_group_id' => $data['wablas_group_id'],
                    'is_default'      => $data['is_default'] ?? false,
                    'keterangan'      => $data['keterangan'],
                ]
            );
        }
    }
}
