<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LayananPublikRequestResource\Pages\CreateLayananPublikRequest;
use App\Filament\Resources\LayananPublikRequestResource\Pages\EditLayananPublikRequest;
use App\Filament\Resources\LayananPublikRequestResource\Pages\ListLayananPublikRequests;
use App\Models\LayananPublik;
use App\Models\LayananPublikRequest;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use UnitEnum;

class LayananPublikRequestResource extends Resource
{
    protected static ?string $model = LayananPublikRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Register Layanan Publik';
    protected static ?string $pluralModelLabel = 'Register Layanan Publik';
    protected static ?string $modelLabel = 'Register Layanan Publik';
    protected static ?string $slug = 'layanan-publik-register';
    protected static string|UnitEnum|null $navigationGroup = 'Layanan Publik';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pemohon')
                ->schema([
                    Select::make('layanan_publik_id')
                        ->label('Layanan Publik')
                        ->options(fn () => LayananPublik::query()
                            ->where('aktif', true)
                            ->orderBy('nama')
                            ->get()
                            ->mapWithKeys(fn (LayananPublik $layanan) => [
                                $layanan->id => self::formatLayananLabel($layanan),
                            ])
                            ->all())
                        ->getSearchResultsUsing(function (string $search): array {
                            $search = trim($search);

                            return LayananPublik::query()
                                ->where('aktif', true)
                                ->when($search !== '', function ($query) use ($search) {
                                    $query->where(function ($builder) use ($search) {
                                        $builder
                                            ->where('nama', 'like', '%' . $search . '%')
                                            ->orWhere('kategori', 'like', '%' . $search . '%');
                                    });
                                })
                                ->orderBy('nama')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (LayananPublik $layanan) => [
                                    $layanan->id => self::formatLayananLabel($layanan),
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }

                            $layanan = LayananPublik::find($value);

                            return $layanan ? self::formatLayananLabel($layanan) : null;
                        })
                        ->createOptionForm([
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
                        ->createOptionUsing(function (array $data): int {
                            return LayananPublik::create($data)->id;
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('nama_pemohon')
                        ->label('Nama Pemohon')
                        ->required(),
                    TextInput::make('no_wa_pemohon')
                        ->label('Nomor WA Pemohon')
                        ->tel()
                        ->helperText('Gunakan format 08xxx atau 62xxx.'),
                    DatePicker::make('tanggal_masuk')
                        ->label('Tanggal Masuk')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Placeholder::make('kode_register')
                        ->label('Kode Register')
                        ->content(fn (?LayananPublikRequest $record) => $record?->kode_register ?? '-')
                        ->visible(fn (?LayananPublikRequest $record) => (bool) $record),
                    Placeholder::make('queue_number')
                        ->label('No Antrian')
                        ->content(fn (?LayananPublikRequest $record) => $record?->queue_number ?? '-')
                        ->visible(fn (?LayananPublikRequest $record) => (bool) $record),
                ])
                ->columns(2),

            Section::make('Status Layanan')
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(LayananPublikRequest::statusOptions())
                        ->default(LayananPublikRequest::STATUS_REGISTERED)
                        ->required()
                        ->live(),
                    DatePicker::make('tanggal_selesai')
                        ->label('Tanggal Selesai')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    TextInput::make('perangkat_desa_nama')
                        ->label('Nama Perangkat Desa')
                        ->visible(fn (callable $get) => $get('status') === LayananPublikRequest::STATUS_PICKED_BY_VILLAGE)
                        ->required(fn (callable $get) => $get('status') === LayananPublikRequest::STATUS_PICKED_BY_VILLAGE),
                    TextInput::make('perangkat_desa_wa')
                        ->label('Nomor WA Perangkat Desa')
                        ->tel()
                        ->visible(fn (callable $get) => $get('status') === LayananPublikRequest::STATUS_PICKED_BY_VILLAGE)
                        ->required(fn (callable $get) => $get('status') === LayananPublikRequest::STATUS_PICKED_BY_VILLAGE),
                    Textarea::make('catatan')
                        ->label('Catatan')
                        ->rows(3),
                    Textarea::make('catatan_progres')
                        ->label('Catatan Progres (log)')
                        ->rows(2)
                        ->dehydrated(false)
                        ->helperText('Opsional, dicatat sebagai log saat status berubah.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode_register')
                    ->label('Kode Register')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('queue_number')
                    ->label('No Antrian')
                    ->sortable(),
                Tables\Columns\TextColumn::make('layanan.nama')
                    ->label('Layanan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('layanan.kategori')
                    ->label('Kategori')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('nama_pemohon')
                    ->label('Pemohon')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => LayananPublikRequest::statusOptions()[$state] ?? $state)
                    ->colors([
                        'warning' => LayananPublikRequest::STATUS_IN_PROGRESS,
                        'success' => LayananPublikRequest::STATUS_COMPLETED,
                        'primary' => LayananPublikRequest::STATUS_READY,
                        'info' => LayananPublikRequest::STATUS_PICKED_BY_VILLAGE,
                        'gray' => LayananPublikRequest::STATUS_REGISTERED,
                        'danger' => LayananPublikRequest::STATUS_CANCELLED,
                    ])
                    ->icons([
                        LayananPublikRequest::STATUS_REGISTERED => 'heroicon-m-ticket',
                        LayananPublikRequest::STATUS_IN_PROGRESS => 'heroicon-m-arrow-path',
                        LayananPublikRequest::STATUS_READY => 'heroicon-m-check-circle',
                        LayananPublikRequest::STATUS_PICKED_BY_VILLAGE => 'heroicon-m-truck',
                        LayananPublikRequest::STATUS_COMPLETED => 'heroicon-m-check-badge',
                        LayananPublikRequest::STATUS_CANCELLED => 'heroicon-m-x-circle',
                    ]),
                Tables\Columns\TextColumn::make('tanggal_masuk')
                    ->label('Tanggal Masuk')
                    ->formatStateUsing(fn ($state) => $state?->format('d/m/Y') ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tanggal_selesai')
                    ->label('Tanggal Selesai')
                    ->formatStateUsing(fn ($state) => $state?->format('d/m/Y') ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('no_wa_pemohon')
                    ->label('WA Pemohon')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('print_register')
                    ->label('Cetak Register')
                    ->icon('heroicon-o-printer')
                    ->url(fn (LayananPublikRequest $record) => URL::signedRoute('public.layanan.register.print', [
                        'kode' => $record->kode_register,
                    ]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.layanan-publik-register');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLayananPublikRequests::route('/'),
            'create' => CreateLayananPublikRequest::route('/create'),
            'edit' => EditLayananPublikRequest::route('/{record}/edit'),
        ];
    }

    protected static function formatLayananLabel(LayananPublik $layanan): string
    {
        if ($layanan->kategori) {
            return $layanan->nama . ' (' . $layanan->kategori . ')';
        }

        return $layanan->nama;
    }
}
