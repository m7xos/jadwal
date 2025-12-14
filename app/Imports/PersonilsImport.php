<?php

namespace App\Imports;

use App\Models\Personil;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PersonilsImport implements ToModel, WithHeadingRow
{
    protected int $rows = 0;

    public function model(array $row)
    {
        // Sesuaikan nama kolom dengan header di file Excel
        // Contoh header:
        // nama | jabatan | no_wa

        // Abaikan baris kalau 'nama' kosong
        if (empty($row['nama'])) {
            return null;
        }

        $this->rows++;

        return new Personil([
            'nama'    => $row['nama'] ?? null,
			'nip'    => $row['nip'] ?? null,
            'jabatan' => $row['jabatan'] ?? null,
            'kategori' => $this->mapKategori($row['kategori'] ?? null),
            'no_wa'   => $this->normalizePhone($row['no_wa'] ?? null),
        ]);
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone); // hapus spasi, +, -, dll

        // Kalau diawali 0, ganti jadi 62 (WhatsApp)
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }

    protected function mapKategori(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'personil kecamatan', 'kecamatan' => 'kecamatan',
            'personil kelurahan', 'kelurahan', 'kel' => 'kelurahan',
            'kades', 'lurah', 'personil kades/lurah', 'kades/lurah' => 'kades_lurah',
            'sekdes', 'selur', 'admin', 'personil sekdes/selur/admin', 'sekdes/selur/admin' => 'sekdes_admin',
            default => null,
        };
    }
}
