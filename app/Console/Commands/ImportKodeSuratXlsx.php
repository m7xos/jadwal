<?php

namespace App\Console\Commands;

use App\Models\KodeSurat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ImportKodeSuratXlsx extends Command
{
    protected $signature = 'kode-surat:import {path? : Path ke file kode_klasifikasi.xlsx} {--sheet=0 : Index sheet yang dipakai}';

    protected $description = 'Import data kode klasifikasi surat dari file Excel.';

    public function handle(): int
    {
        $path = $this->argument('path') ?: storage_path('app/kode_klasifikasi.xlsx');
        $sheetIndex = (int) $this->option('sheet');

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $sheets = Excel::toArray([], $path);
        $rows = $sheets[$sheetIndex] ?? [];

        if (empty($rows)) {
            $this->error('Sheet kosong atau tidak ditemukan.');
            return self::FAILURE;
        }

        $header = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            $rows[0] ?? []
        );

        $hasHeader = $this->rowLooksLikeHeader($header);

        $kodeIndex = $hasHeader ? $this->findColumnIndex($header, ['kode', 'kode_klasifikasi', 'kode surat', 'kode_surat']) : 0;
        $ketIndex = $hasHeader ? $this->findColumnIndex($header, ['keterangan', 'uraian', 'nama', 'perihal']) : 1;

        if ($kodeIndex === null || $ketIndex === null) {
            $this->error('Kolom kode/keterangan tidak ditemukan. Pastikan header berisi "kode" dan "keterangan".');
            return self::FAILURE;
        }

        $start = $hasHeader ? 1 : 0;
        $now = Carbon::now();
        $payload = [];

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i] ?? [];

            $kode = trim((string) ($row[$kodeIndex] ?? ''));
            $keterangan = trim((string) ($row[$ketIndex] ?? ''));

            if ($kode === '' || $keterangan === '') {
                continue;
            }

            $payload[] = [
                'kode' => $kode,
                'keterangan' => $keterangan,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($payload)) {
            $this->warn('Tidak ada baris yang dapat diimpor.');
            return self::SUCCESS;
        }

        KodeSurat::upsert($payload, ['kode'], ['keterangan', 'updated_at']);

        $this->info('Import selesai: ' . count($payload) . ' kode diproses.');

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $header
     */
    protected function rowLooksLikeHeader(array $header): bool
    {
        $needle = implode(' ', $header);

        return str_contains($needle, 'kode')
            || str_contains($needle, 'keterangan')
            || str_contains($needle, 'uraian');
    }

    /**
     * @param array<int, string> $header
     * @param array<int, string> $candidates
     */
    protected function findColumnIndex(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $header, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
