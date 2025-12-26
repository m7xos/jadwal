<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuratKeluarResource\Pages\CreateSuratKeluar;
use App\Filament\Resources\SuratKeluarResource\Pages\EditSuratKeluar;
use App\Filament\Resources\SuratKeluarResource\Pages\ListSuratKeluars;
use App\Models\KodeSurat;
use App\Models\SuratKeluar;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use UnitEnum;

class SuratKeluarResource extends Resource
{
    protected static ?string $model = SuratKeluar::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Surat Keluar';
    protected static ?string $pluralModelLabel = 'Surat Keluar';
    protected static ?string $modelLabel = 'Surat Keluar';
    protected static ?string $slug = 'surat-keluar';
    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Surat';
    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Surat Keluar')
                ->schema([
                    Select::make('jenis_nomor')
                        ->label('Jenis Nomor')
                        ->options([
                            'master' => 'Nomor baru',
                            'sisipan' => 'Nomor sisipan',
                        ])
                        ->default('master')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === 'master') {
                                $set('master_id', null);
                            } else {
                                $set('kode_surat_id', null);
                            }
                        })
                        ->hiddenOn('edit'),

                    Select::make('kode_surat_id')
                        ->label('Kode Klasifikasi')
                        ->options(fn () => KodeSurat::query()
                            ->orderBy('kode')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (KodeSurat $kode) => [
                                $kode->id => $kode->kode . ' - ' . $kode->keterangan,
                            ])
                            ->all())
                        ->getSearchResultsUsing(function (string $search): array {
                            $search = trim($search);

                            return KodeSurat::query()
                                ->when($search !== '', function ($query) use ($search) {
                                    $query->where(function ($builder) use ($search) {
                                        $builder
                                            ->where('kode', 'like', '%' . $search . '%')
                                            ->orWhere('keterangan', 'like', '%' . $search . '%');
                                    });
                                })
                                ->orderBy('kode')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (KodeSurat $kode) => [
                                    $kode->id => $kode->kode . ' - ' . $kode->keterangan,
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $kode = KodeSurat::find($value);
                            if (! $kode) {
                                return null;
                            }

                            return $kode->kode . ' - ' . $kode->keterangan;
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Ketik kode atau keterangan')
                        ->required(fn (callable $get) => ($get('jenis_nomor') ?? 'master') === 'master')
                        ->live()
                        ->visible(fn (callable $get) => ($get('jenis_nomor') ?? 'master') === 'master')
                        ->hiddenOn('edit'),

                    Select::make('master_id')
                        ->label('Nomor Master')
                        ->options(fn () => SuratKeluar::query()
                            ->where('nomor_sisipan', 0)
                            ->orderByDesc('created_at')
                            ->with('kodeSurat')
                            ->get()
                            ->mapWithKeys(fn (SuratKeluar $surat) => [
                                $surat->id => $surat->nomor_label . ' · ' . $surat->perihal,
                            ]))
                        ->searchable()
                        ->preload()
                        ->required(fn (callable $get) => ($get('jenis_nomor') ?? 'master') === 'sisipan')
                        ->live()
                        ->visible(fn (callable $get) => ($get('jenis_nomor') ?? 'master') === 'sisipan')
                        ->hiddenOn('edit'),

                    Textarea::make('perihal')
                        ->label('Hal / Perihal')
                        ->rows(3)
                        ->required(),

                    DatePicker::make('tanggal_surat')
                        ->label('Tanggal Surat')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Placeholder::make('nomor_preview')
                        ->label('Preview Nomor Surat')
                        ->content(fn (callable $get) => app(\App\Services\SuratKeluarService::class)
                            ->previewNextMasterNumberText($get('kode_surat_id'), $get('master_id')))
                        ->visible(fn (callable $get) => filled($get('kode_surat_id')) || filled($get('master_id')))
                        ->hiddenOn('edit')
                        ->dehydrated(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_label')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('perihal')
                    ->label('Perihal')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state === 'booked' ? 'Nomor sudah dibooking' : 'Terbit')
                    ->colors([
                        'warning' => 'booked',
                        'success' => 'issued',
                    ])
                    ->icons([
                        'booked' => 'heroicon-m-bookmark',
                        'issued' => 'heroicon-m-check-badge',
                    ]),
                Tables\Columns\TextColumn::make('tanggal_surat')
                    ->label('Tanggal Surat')
                    ->formatStateUsing(fn ($state) => $state?->format('d/m/Y') ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booked_at')
                    ->label('Tanggal Booking')
                    ->formatStateUsing(fn ($state) => $state?->format('d/m/Y') ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kodeSurat.kode')
                    ->label('Kode')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.surat-keluar');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratKeluars::route('/'),
            'create' => CreateSuratKeluar::route('/create'),
            'edit' => EditSuratKeluar::route('/{record}/edit'),
        ];
    }
}
