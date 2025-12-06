<?php

namespace App\Imports;

use App\Models\VehicleAsset;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VehicleAssetsImport implements ToModel, WithHeadingRow, WithCustomCsvSettings
{
    public function model(array $row): VehicleAsset
    {
        $payload = [
            'id_pemda' => $row['idpemda'] ?? null,
            'kode_upb' => $row['kode_upb'] ?? null,
            'nama_upb' => $row['nama_upb'] ?? null,
            'kode_aset' => $row['kode_aset'] ?? null,
            'nama_aset' => $row['nama_aset'] ?? null,
            'reg' => $row['reg'] ?? null,
            'merk_type' => $row['merk_type'] ?? null,
            'ukuran_cc' => $row['ukuran_cc'] ?? null,
            'bahan' => $row['bahan'] ?? null,
            'tahun' => $this->parseDate($row['tahun'] ?? null),
            'nomor_pabrik' => $row['nomor_pabrik'] ?? null,
            'nomor_rangka' => $row['nomor_rangka'] ?? null,
            'nomor_mesin' => $row['nomor_mesin'] ?? null,
            'nomor_polisi' => isset($row['nomor_polisi']) ? str_replace(' ', '', $row['nomor_polisi']) : null,
            'nomor_bpkb' => $row['nomor_bpkb'] ?? null,
            'harga' => $this->parsePrice($row['harga'] ?? null),
            'keterangan' => $row['keterangan'] ?? null,
        ];

        return VehicleAsset::updateOrCreate(
            ['nomor_polisi' => $payload['nomor_polisi']],
            $payload,
        );
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
        ];
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $th) {
            return null;
        }
    }

    protected function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(['.', ' '], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
