<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LayananPublikResource\Pages\CreateLayananPublik;
use App\Filament\Resources\LayananPublikResource\Pages\EditLayananPublik;
use App\Filament\Resources\LayananPublikResource\Pages\ListLayananPubliks;
use App\Models\LayananPublik;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class LayananPublikResource extends Resource
{
    protected static ?string $model = LayananPublik::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationLabel = 'Layanan Publik';
    protected static ?string $pluralModelLabel = 'Layanan Publik';
    protected static ?string $modelLabel = 'Layanan Publik';
    protected static ?string $slug = 'layanan-publik';
    protected static string|UnitEnum|null $navigationGroup = 'Layanan Publik';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Layanan Publik')
                ->schema([
                    TextInput::make('nama')
                        ->label('Nama Layanan')
                        ->required(),
                    Textarea::make('deskripsi')
                        ->label('Deskripsi')
                        ->rows(3),
                    Toggle::make('aktif')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Layanan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('nama')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.layanan-publik');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLayananPubliks::route('/'),
            'create' => CreateLayananPublik::route('/create'),
            'edit' => EditLayananPublik::route('/{record}/edit'),
        ];
    }
}
