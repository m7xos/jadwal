<?php

namespace App\Filament\Resources\VehicleTaxes\Pages;

use App\Filament\Resources\VehicleTaxes\VehicleTaxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicleTaxes extends ListRecords
{
    protected static string $resource = VehicleTaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
