<?php

namespace App\Filament\Resources\LayananPublikRequestResource\Pages;

use App\Filament\Resources\LayananPublikRequestResource;
use App\Services\LayananPublikRequestService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateLayananPublikRequest extends CreateRecord
{
    protected static string $resource = LayananPublikRequestResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function handleRecordCreation(array $data): Model
    {
        $personilId = auth()->user()?->id;

        /** @var LayananPublikRequestService $service */
        $service = app(LayananPublikRequestService::class);

        return $service->createRequest($data, $personilId);
    }

    protected function afterCreate(): void
    {
        $kode = $this->record?->kode_register;

        if (! $kode) {
            return;
        }

        Notification::make()
            ->title('Register layanan dibuat')
            ->body('Kode register: ' . $kode)
            ->success()
            ->send();
    }
}
