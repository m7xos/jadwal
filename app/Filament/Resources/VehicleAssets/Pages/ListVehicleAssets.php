<?php

namespace App\Filament\Resources\VehicleAssets\Pages;

use App\Filament\Resources\VehicleAssets\VehicleAssetResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleAssets extends ListRecords
{
    protected static string $resource = VehicleAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
