<?php

namespace App\Filament\Resources\KegiatanPkkResource\Pages;

use App\Filament\Resources\KegiatanPkkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKegiatanPkk extends ListRecords
{
    protected static string $resource = KegiatanPkkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
