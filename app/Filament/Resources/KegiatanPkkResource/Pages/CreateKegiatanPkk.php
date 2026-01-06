<?php

namespace App\Filament\Resources\KegiatanPkkResource\Pages;

use App\Filament\Resources\KegiatanPkkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKegiatanPkk extends CreateRecord
{
    protected static string $resource = KegiatanPkkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_pkk'] = true;

        return $data;
    }
}
