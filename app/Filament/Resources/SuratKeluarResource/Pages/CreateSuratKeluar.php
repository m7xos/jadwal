<?php

namespace App\Filament\Resources\SuratKeluarResource\Pages;

use App\Filament\Resources\SuratKeluarResource;
use App\Models\KodeSurat;
use App\Models\SuratKeluar;
use App\Services\SuratKeluarService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateSuratKeluar extends CreateRecord
{
    protected static string $resource = SuratKeluarResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var SuratKeluarService $service */
        $service = app(SuratKeluarService::class);

        $requester = auth()->user();
        $requestedPersonilId = $requester?->id;
        $selectedPersonilId = $data['requested_by_personil_id'] ?? null;

        if ($requester?->isInFinishWhitelist() === true && $selectedPersonilId) {
            $requestedPersonilId = (int) $selectedPersonilId;
        }

        $context = [
            'requested_by_personil_id' => $requestedPersonilId,
            'source' => 'manual',
            'tanggal_surat' => $data['tanggal_surat'] ?? now(),
        ];

        if (($data['jenis_nomor'] ?? 'master') === 'sisipan') {
            $master = SuratKeluar::findOrFail($data['master_id']);
            return $service->createSisipan($master, (string) $data['perihal'], $context);
        }

        $kode = KodeSurat::findOrFail($data['kode_surat_id']);

        return $service->createMaster($kode, (string) $data['perihal'], $context);
    }
}
