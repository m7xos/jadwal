<?php

namespace App\Filament\Resources\LayananPublikRequestResource\Pages;

use App\Filament\Resources\LayananPublikRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLayananPublikRequests extends ListRecords
{
    protected static string $resource = LayananPublikRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Register Layanan'),
        ];
    }
}
