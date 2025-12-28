<?php

namespace App\Filament\Resources\WaInboxMessageResource\Pages;

use App\Filament\Resources\WaInboxMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListWaInboxMessages extends ListRecords
{
    protected static string $resource = WaInboxMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
