<?php

namespace App\Filament\Resources\LayananPublikRequestResource\Pages;

use App\Filament\Resources\LayananPublikRequestResource;
use App\Services\LayananPublikRequestService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditLayananPublikRequest extends EditRecord
{
    protected static string $resource = LayananPublikRequestResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $personilId = auth()->user()?->id;

        /** @var LayananPublikRequestService $service */
        $service = app(LayananPublikRequestService::class);

        return $service->updateRequest($record, $data, $personilId);
    }
}
