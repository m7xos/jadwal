<?php

namespace App\Filament\Resources\FollowUpReminderResource\Pages;

use App\Filament\Resources\FollowUpReminderResource;
use App\Services\FollowUpReminderService;
use Filament\Resources\Pages\CreateRecord;

class CreateFollowUpReminder extends CreateRecord
{
    protected static string $resource = FollowUpReminderResource::class;

    protected function afterCreate(): void
    {
        /** @var FollowUpReminderService $service */
        $service = app(FollowUpReminderService::class);
        $service->send($this->record);
    }
}
