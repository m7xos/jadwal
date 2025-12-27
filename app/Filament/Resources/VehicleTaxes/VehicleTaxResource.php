<?php

namespace App\Filament\Resources\VehicleTaxes;

use App\Filament\Resources\VehicleTaxes\Pages\CreateVehicleTax;
use App\Filament\Resources\VehicleTaxes\Pages\EditVehicleTax;
use App\Filament\Resources\VehicleTaxes\Pages\ListVehicleTaxes;
use App\Filament\Resources\VehicleTaxes\Schemas\VehicleTaxForm;
use App\Filament\Resources\VehicleTaxes\Tables\VehicleTaxesTable;
use App\Models\VehicleTax;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class VehicleTaxResource extends Resource
{
    protected static ?string $model = VehicleTax::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Pajak Kendaraan';
    protected static ?string $pluralModelLabel = 'Pajak Kendaraan';
    protected static ?string $modelLabel = 'Pajak Kendaraan';
    protected static ?string $slug = 'pajak-kendaraan';

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return VehicleTaxForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleTaxesTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.pajak-kendaraan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleTaxes::route('/'),
            'create' => CreateVehicleTax::route('/create'),
            'edit' => EditVehicleTax::route('/{record}/edit'),
        ];
    }
}
