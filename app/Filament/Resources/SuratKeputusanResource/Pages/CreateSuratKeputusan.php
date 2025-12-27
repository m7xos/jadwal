<?php

namespace App\Filament\Resources\SuratKeputusanResource\Pages;

use App\Filament\Resources\SuratKeputusanResource;
use App\Models\KodeSurat;
use App\Models\SuratKeputusan;
use App\Services\SuratKeputusanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSuratKeputusan extends CreateRecord
{
    protected static string $resource = SuratKeputusanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var SuratKeputusanService $service */
        $service = app(SuratKeputusanService::class);

        $context = [
            'source' => 'manual',
            'tanggal_surat' => $data['tanggal_surat'] ?? now(),
            'tanggal_diundangkan' => $data['tanggal_diundangkan'] ?? null,
        ];

        if (($data['jenis_nomor'] ?? 'master') === 'sisipan') {
            $master = SuratKeputusan::findOrFail($data['master_id']);
            return $service->createSisipan($master, (string) $data['perihal'], $context);
        }

        $kode = KodeSurat::findOrFail($data['kode_surat_id']);

        return $service->createMaster($kode, (string) $data['perihal'], $context);
    }
}
