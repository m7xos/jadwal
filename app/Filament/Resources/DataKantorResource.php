<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DataKantorResource\Pages\CreateDataKantor;
use App\Filament\Resources\DataKantorResource\Pages\EditDataKantor;
use App\Filament\Resources\DataKantorResource\Pages\ListDataKantors;
use App\Models\DataKantor;
use App\Services\PdfCompressor;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class DataKantorResource extends Resource
{
    protected static ?string $model = DataKantor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationLabel = 'Data Kantor';
    protected static ?string $pluralModelLabel = 'Data Kantor';
    protected static ?string $modelLabel = 'Data Kantor';
    protected static ?string $slug = 'data-kantor';
    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Dokumen Kantor')
                ->schema([
                    Select::make('jenis_dokumen')
                        ->label('Jenis Dokumen')
                        ->options([
                            'DPA' => 'DPA',
                            'RKA' => 'RKA',
                            'Renja' => 'Renja',
                            'Lainnya' => 'Dokumen Lainnya',
                        ])
                        ->required()
                        ->native(false),

                    TextInput::make('nama_dokumen')
                        ->label('Nama Dokumen')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Gunakan nama lengkap dokumen untuk memudahkan pencarian.'),

                    TextInput::make('tahun')
                        ->label('Tahun')
                        ->numeric()
                        ->minValue(2000)
                        ->maxValue((int) now()->year + 1)
                        ->helperText('Opsional, isi tahun dokumen jika ada.'),

                    Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->rows(3)
                        ->columnSpanFull(),

                    FileUpload::make('berkas')
                        ->label('Berkas Dokumen')
                        ->disk('public')
                        ->directory('data-kantor')
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->helperText('Format yang didukung: PDF, DOC, DOCX, XLS, XLSX.')
                        ->deleteUploadedFileUsing(function ($file): void {
                            if ($file instanceof TemporaryUploadedFile) {
                                $file->delete();
                            }

                            if (is_string($file)) {
                                Storage::disk('public')->delete($file);
                            }
                        })
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string =>
                                static::generateStoredFilename($file)
                        )
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            if (! $state) {
                                return;
                            }

                            $storedPath = static::storeUploadedBerkas($state, $get('berkas'));

                            if (! $storedPath) {
                                return;
                            }

                            $set('berkas', $storedPath);
                        })
                        ->openable()
                        ->downloadable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jenis_dokumen')
                    ->label('Jenis')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama_dokumen')
                    ->label('Nama Dokumen')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('tahun')
                    ->label('Tahun')
                    ->sortable(),
                Tables\Columns\TextColumn::make('berkas')
                    ->label('Berkas')
                    ->formatStateUsing(fn ($state) => $state ? basename($state) : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (DataKantor $record) => filled($record->berkas_url))
                    ->url(fn (DataKantor $record) => $record->berkas_url ?? '#')
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
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.data-kantor');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataKantors::route('/'),
            'create' => CreateDataKantor::route('/create'),
            'edit' => EditDataKantor::route('/{record}/edit'),
        ];
    }

    protected static function storeUploadedBerkas(
        string|TemporaryUploadedFile $state,
        ?string $currentPath = null
    ): ?string {
        if ($state instanceof TemporaryUploadedFile) {
            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
            }

            $filename = static::generateStoredFilename($state);
            $path = $state->storeAs('data-kantor', $filename, 'public');

            if ($path && static::shouldCompressPdf($state)) {
                static::compressUploadedPdf($path);
            }

            return $path;
        }

        return is_string($state) ? $state : null;
    }

    protected static function shouldCompressPdf(TemporaryUploadedFile $file): bool
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== '') {
            return $extension === 'pdf';
        }

        return $file->getMimeType() === 'application/pdf';
    }

    protected static function generateStoredFilename(TemporaryUploadedFile $file): string
    {
        $original = $file->getClientOriginalName();
        $sanitized = static::sanitizeFilename($original);

        if ($sanitized === '') {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fallback = $extension !== '' ? "file.{$extension}" : 'file';
            $sanitized = $fallback;
        }

        return now()->format('Ymd_His') . '_' . $sanitized;
    }

    protected static function sanitizeFilename(string $originalName): string
    {
        $trimmed = trim($originalName);

        if ($trimmed === '') {
            return '';
        }

        $normalizedSpaces = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return str_replace(' ', '-', $normalizedSpaces);
    }

    protected static function compressUploadedPdf(string $storedPath): void
    {
        $absolutePath = Storage::disk('public')->path($storedPath);

        if (! is_file($absolutePath)) {
            return;
        }

        $targetBytes = static::uploadLimitBytes();

        if ($targetBytes !== null) {
            $targetBytes = (int) floor($targetBytes * 0.95);
        }

        /** @var PdfCompressor $compressor */
        $compressor = app(PdfCompressor::class);
        $compressor->compress($absolutePath, $targetBytes);
    }

    protected static function uploadLimitBytes(): ?int
    {
        $limits = array_filter([
            static::parseIniSize(ini_get('upload_max_filesize')),
            static::parseIniSize(ini_get('post_max_size')),
            static::livewireUploadLimitBytes(),
        ]);

        if (empty($limits)) {
            return null;
        }

        return (int) min($limits);
    }

    protected static function livewireUploadLimitBytes(): ?int
    {
        $maxMb = config('livewire.temporary_file_upload.max_upload_size');

        if ($maxMb === null) {
            return null;
        }

        return (int) $maxMb * 1024 * 1024;
    }

    protected static function parseIniSize(string|false|null $value): ?int
    {
        if ($value === false || $value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
