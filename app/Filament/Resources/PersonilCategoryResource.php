<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonilCategoryResource\Pages\CreatePersonilCategory;
use App\Filament\Resources\PersonilCategoryResource\Pages\EditPersonilCategory;
use App\Filament\Resources\PersonilCategoryResource\Pages\ListPersonilCategories;
use App\Models\PersonilCategory;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use UnitEnum;

class PersonilCategoryResource extends Resource
{
    protected static ?string $model = PersonilCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Kategori Personil';
    protected static ?string $pluralModelLabel = 'Kategori Personil';
    protected static ?string $modelLabel = 'Kategori Personil';
    protected static ?string $slug = 'personil-categories';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kategori')
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(50)
                        ->unique(table: PersonilCategory::class, column: 'slug', ignoreRecord: true)
                        ->helperText('Slug unik, gunakan huruf kecil tanpa spasi (contoh: kecamatan, sekdes_admin).'),

                    TextInput::make('nama')
                        ->label('Nama')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('label_broadcast')
                        ->label('Label broadcast')
                        ->maxLength(120)
                        ->helperText('Label yang ditampilkan di pesan WA saat kategori ini dipilih.'),

                    TextInput::make('keterangan')
                        ->label('Keterangan')
                        ->maxLength(191),

                    TextInput::make('urutan')
                        ->label('Urutan')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
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
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label_broadcast')
                    ->label('Label broadcast')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('urutan')
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
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.personil-categories');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPersonilCategories::route('/'),
            'create' => CreatePersonilCategory::route('/create'),
            'edit'   => EditPersonilCategory::route('/{record}/edit'),
        ];
    }
}
