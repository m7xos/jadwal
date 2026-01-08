<?php

namespace App\Filament\Resources\DataKantorResource\Pages;

use App\Filament\Resources\DataKantorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDataKantors extends ListRecords
{
    protected static string $resource = DataKantorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
