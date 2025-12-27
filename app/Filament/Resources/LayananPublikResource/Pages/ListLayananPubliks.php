<?php

namespace App\Filament\Resources\LayananPublikResource\Pages;

use App\Filament\Resources\LayananPublikResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLayananPubliks extends ListRecords
{
    protected static string $resource = LayananPublikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Layanan Publik'),
        ];
    }
}
