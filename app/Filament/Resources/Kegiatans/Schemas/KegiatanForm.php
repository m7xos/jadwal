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
                // =========================
                // SECTION: INFORMASI KEGIATAN
                // =========================
                Section::make('Informasi Kegiatan')
                    ->schema([
                        // Upload surat undangan (PDF)
                        FileUpload::make('surat_undangan')
                            ->label('Surat Undangan (PDF)')
                            ->disk('public')
                            ->directory('surat-undangan')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['application/pdf'])
                            ->required()
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string =>
                                    now()->format('Ymd_His') . '_' . $file->getClientOriginalName()
                            )
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }

                                /** @var NomorSuratExtractor $extractor */
                                $extractor = app(NomorSuratExtractor::class);

                                // $state = path relatif di disk 'public', misal "surat-undangan/xxx.pdf"

                                // ===== NOMOR SURAT =====
                                $nomor = $extractor->extract($state);
                                if (! empty($nomor)) {
                                    $set('nomor', $nomor);
                                }

                                // ===== HAL / PERIHAL → NAMA KEGIATAN =====
                                if (method_exists($extractor, 'extractPerihal')) {
                                    $perihal = $extractor->extractPerihal($state);
                                    if (! empty($perihal)) {
                                        $set('nama_kegiatan', $perihal);
                                    }
                                }
                            }),

                        TextInput::make('nomor')
                            ->label('Nomor Surat')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Akan otomatis diisi dari PDF jika pola nomor surat dikenali.'),

                        TextInput::make('nama_kegiatan')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(500) // dibuat lebih panjang
                            ->helperText('Diambil otomatis dari HAL/PERIHAL surat (bisa diubah).'),

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
                    ->columns(2)         // isi form dibagi 2 kolom
                    ->columnSpanFull(),  // section ini full lebar

                // =========================
                // SECTION: PERSONIL YANG MENGHADIRI (DI BAWAH)
                // =========================
                Section::make('Personil yang Menghadiri')
                    ->schema([
                        Select::make('personils')
                            ->label('Pilih Personil')
                            ->relationship('personils', 'nama')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Pilih personil yang akan menghadiri kegiatan ini.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
