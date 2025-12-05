<?php

namespace App\Filament\Resources\TindakLanjutReminderLogResource\Pages;

use App\Filament\Resources\TindakLanjutReminderLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTindakLanjutReminderLogs extends ListRecords
{
    protected static string $resource = TindakLanjutReminderLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
