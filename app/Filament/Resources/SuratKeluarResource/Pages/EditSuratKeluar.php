<?php

namespace App\Filament\Resources\SuratKeluarResource\Pages;

use App\Filament\Resources\SuratKeluarResource;
use App\Models\SuratKeluar;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSuratKeluar extends EditRecord
{
    protected static string $resource = SuratKeluarResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user?->isInFinishWhitelist() !== true) {
            unset($data['requested_by_personil_id']);
        }

        if (($this->record->status ?? SuratKeluar::STATUS_ISSUED) === SuratKeluar::STATUS_BOOKED) {
            $perihal = trim((string) ($data['perihal'] ?? ''));
            $tanggalSurat = $data['tanggal_surat'] ?? null;

            if ($perihal !== '' && $perihal !== SuratKeluar::BOOKED_PLACEHOLDER && ! empty($tanggalSurat)) {
                $data['status'] = SuratKeluar::STATUS_ISSUED;
            }
        }

        return $data;
    }
}
