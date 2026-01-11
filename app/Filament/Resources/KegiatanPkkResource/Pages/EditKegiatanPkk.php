<?php

namespace App\Filament\Resources\KegiatanPkkResource\Pages;

use App\Filament\Resources\KegiatanPkkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKegiatanPkk extends EditRecord
{
    protected static string $resource = KegiatanPkkResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_pkk'] = true;

        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('disposisi')
                ->label('Disposisi')
                ->url(fn () => route('kegiatan.disposisi', $this->record))
                ->openUrlInNewTab()
                ->color('gray'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
