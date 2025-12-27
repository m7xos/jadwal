<?php

namespace App\Filament\Resources\Kegiatans;

use App\Filament\Resources\Kegiatans\Pages\CreateKegiatan;
use App\Filament\Resources\Kegiatans\Pages\EditKegiatan;
use App\Filament\Resources\Kegiatans\Pages\ListKegiatans;
use App\Filament\Resources\Kegiatans\Schemas\KegiatanForm;
use App\Filament\Resources\Kegiatans\Tables\KegiatansTable;
use App\Models\Kegiatan;
use App\Support\RoleAccess;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;      // <-- PENTING, tambahkan ini
use BackedEnum;    // <-- sekalian untuk $navigationIcon (lihat di bawah)

class KegiatanResource extends Resource
{
    protected static ?string $model = Kegiatan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Agenda Kegiatan Kantor';
    protected static ?string $pluralModelLabel = 'Agenda Kegiatan';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Kegiatan';
  
    public static function form(Schema $schema): Schema
    {
        return KegiatanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KegiatansTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.kegiatans');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListKegiatans::route('/'),
            'create' => CreateKegiatan::route('/create'),
            'edit'   => EditKegiatan::route('/{record}/edit'),
        ];
    }
}
