<?php

namespace App\Filament\Resources\PersonilCategoryResource\Pages;

use App\Filament\Resources\PersonilCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonilCategories extends ListRecords
{
    protected static string $resource = PersonilCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
