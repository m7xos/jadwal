<?php

namespace App\Filament\Resources\SuratKeputusanResource\Pages;

use App\Filament\Resources\SuratKeputusanResource;
use App\Models\SuratKeputusan;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSuratKeputusan extends EditRecord
{
    protected static string $resource = SuratKeputusanResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($this->record->status ?? SuratKeputusan::STATUS_ISSUED) === SuratKeputusan::STATUS_BOOKED) {
            $perihal = trim((string) ($data['perihal'] ?? ''));
            $tanggalSurat = $data['tanggal_surat'] ?? null;

            if ($perihal !== '' && $perihal !== SuratKeputusan::BOOKED_PLACEHOLDER && ! empty($tanggalSurat)) {
                $data['status'] = SuratKeputusan::STATUS_ISSUED;
            }
        }

        return $data;
    }
}
