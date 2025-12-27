<?php

namespace App\Filament\Resources\FollowUpReminderResource\Pages;

use App\Filament\Resources\FollowUpReminderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFollowUpReminders extends ListRecords
{
    protected static string $resource = FollowUpReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
