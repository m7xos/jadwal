<?php

namespace Database\Seeders;

use App\Models\KodeSurat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class KodeSuratSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/kode_surats.txt');

        if (! File::exists($path)) {
            $this->command?->warn("File data kode surat tidak ditemukan: {$path}");

            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        $rows = collect($lines)
            ->map(function (string $line) {
                $line = trim($line, "\xEF\xBB\xBF\" \t"); // buang BOM/quote/spasi

                [$kode, $ket] = array_pad(explode(';', $line, 2), 2, null);

                return [
                    'kode' => trim((string) $kode),
                    'keterangan' => $ket === null ? null : trim($ket),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->filter(fn (array $row) => $row['kode'] !== '')
            ->unique('kode')
            ->values()
            ->all();

        if (empty($rows)) {
            $this->command?->warn('Data kode surat kosong. Periksa format file (kode;keterangan) per baris).');

            return;
        }

        KodeSurat::upsert($rows, ['kode'], ['keterangan', 'updated_at']);

        $this->command?->info('Seeder kode_surats selesai: ' . count($rows) . ' kode diproses.');
    }
}
