<?php

namespace Database\Seeders;

use App\Models\PersonilCategory;
use Illuminate\Database\Seeder;

class PersonilCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (PersonilCategory::defaultSeeds() as $data) {
            PersonilCategory::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'nama' => $data['nama'],
                    'urutan' => $data['urutan'],
                    'is_active' => true,
                ],
            );
        }
    }
}
