<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KodeSuratResource\Pages\ListKodeSurats;
use App\Models\KodeSurat;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class KodeSuratResource extends Resource
{
    protected static ?string $model = KodeSurat::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Klasifikasi Surat';
    protected static ?string $pluralModelLabel = 'Klasifikasi Surat';
    protected static ?string $modelLabel = 'Klasifikasi Surat';
    protected static ?string $slug = 'kode-surat';
    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Surat';
    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Klasifikasi Surat')
                ->schema([
                    TextInput::make('kode')
                        ->label('Kode')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('keterangan')
                        ->label('Keterangan')
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('kode');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.kode-surat');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKodeSurats::route('/'),
        ];
    }
}
