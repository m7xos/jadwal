<?php

namespace App\Filament\Resources\Kegiatans\Schemas;

use App\Services\NomorSuratExtractor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class KegiatanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kegiatan')
                    ->schema([
                        FileUpload::make('surat_undangan')
                            ->label('Surat Undangan (PDF)')
                            ->disk('public')
                            ->directory('surat-undangan')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['application/pdf'])
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string =>
                                    now()->format('Ymd_His') . '_' . $file->getClientOriginalName()
                            )
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }

                                // Tentukan path file yang benar
                                if ($state instanceof TemporaryUploadedFile) {
                                    // Saat baru upload → file temp
                                    $path = $state->getRealPath();
                                } elseif (is_string($state)) {
                                    // Saat edit / sudah tersimpan → path relatif atau absolut
                                    $path = $state;
                                } else {
                                    return;
                                }

                                /** @var NomorSuratExtractor $extractor */
                                $extractor = app(NomorSuratExtractor::class);

                                $nomor = $extractor->extract($path);

                                if ($nomor) {
                                    $set('nomor', $nomor);
                                }
                            }),

                        TextInput::make('nomor')
                            ->label('Nomor Surat')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Akan otomatis diisi dari PDF jika pola "Nomor :" atau "Nomor" ditemukan.'),

                        TextInput::make('nama_kegiatan')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('tanggal')
                            ->label('Hari / Tanggal')
                            ->required()
                            ->displayFormat('d-m-Y'),

                        TextInput::make('waktu')
                            ->label('Waktu')
                            ->placeholder('Contoh: 09.00 - 11.00 WIB')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('tempat')
                            ->label('Tempat')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Personil yang Menghadiri')
                    ->schema([
                        Select::make('personils')
                            ->label('Pilih Personil')
                            ->relationship('personils', 'nama')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih personil yang akan menghadiri kegiatan ini.'),
                    ]),
            ]);
    }
}
