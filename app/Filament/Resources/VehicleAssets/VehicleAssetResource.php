<?php

namespace App\Filament\Resources\VehicleAssets;

use App\Filament\Resources\VehicleAssets\Pages\CreateVehicleAsset;
use App\Filament\Resources\VehicleAssets\Pages\EditVehicleAsset;
use App\Filament\Resources\VehicleAssets\Pages\ListVehicleAssets;
use App\Filament\Resources\VehicleAssets\Schemas\VehicleAssetForm;
use App\Filament\Resources\VehicleAssets\Tables\VehicleAssetsTable;
use App\Imports\VehicleAssetsImport;
use App\Models\VehicleAsset;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class VehicleAssetResource extends Resource
{
    protected static ?string $model = VehicleAsset::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Data Kendaraan';
    protected static ?string $pluralModelLabel = 'Data Kendaraan';
    protected static ?string $modelLabel = 'Kendaraan';
    protected static ?string $slug = 'vehicle-assets';

    protected static string|UnitEnum|null $navigationGroup = 'Pengingat';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return VehicleAssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $table = VehicleAssetsTable::configure($table);

        return $table->headerActions([
            Action::make('importVehicleAssets')
                ->label('Import dari Excel/CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel/CSV')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->directory('import/kendaraan')
                        ->disk('public')
                        ->required()
                        ->helperText('Gunakan contoh file Import di Public/Template;'),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('public')->path($data['file']);

                    $import = new VehicleAssetsImport();
                    Excel::import($import, $path);

                    Notification::make()
                        ->title('Import data kendaraan berhasil')
                        ->body('Data sudah disimpan/diupdate berdasarkan nomor polisi.')
                        ->success()
                        ->send();
                })
                ->modalHeading('Import Data Kendaraan')
                ->modalSubmitActionLabel('Import')
                ->modalWidth('md'),
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.vehicle-assets');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleAssets::route('/'),
            'create' => CreateVehicleAsset::route('/create'),
            'edit' => EditVehicleAsset::route('/{record}/edit'),
        ];
    }
}
