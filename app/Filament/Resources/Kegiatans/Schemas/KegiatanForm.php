<?php

namespace App\Filament\Resources\Kegiatans\Schemas;

use App\Services\NomorSuratExtractor;
use App\Models\Personil;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;     // ⬅️ TAMBAHAN
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
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
                        Radio::make('jenis_surat')
                            ->label('Jenis Surat')
                            ->options([
                                'undangan' => 'Surat Undangan (ditampilkan di dashboard publik)',
                                'kegiatan_tindak_lanjut' => 'Surat Kegiatan (ada batas waktu tindak lanjut)',
                            ])
                            ->live()
                            ->default('undangan')
                            ->inline()
                            ->helperText('Pilih "Surat Kegiatan" untuk surat masuk non-undangan yang memiliki tenggat tindak lanjut.'),

                        FileUpload::make('surat_undangan')
                            ->label('Surat Undangan (PDF)')
                            ->disk('public')
                            ->directory('surat-undangan')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['application/pdf'])
                            ->required(fn (Get $get) => $get('jenis_surat') === 'undangan')
                            ->hidden(fn (Get $get) => $get('jenis_surat') === 'kegiatan_tindak_lanjut')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string =>
                                    now()->format('Ymd_His') . '_' . $file->getClientOriginalName()
                            )
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }

                                // ==== NORMALISASI STATE JADI PATH ====
                                $path = null;

                                if ($state instanceof TemporaryUploadedFile) {
                                    $path = $state->getRealPath() ?: $state->getPathname();
                                } elseif (is_string($state)) {
                                    $path = $state;
                                } elseif (is_array($state) && isset($state[0])) {
                                    $first = $state[0];

                                    if ($first instanceof TemporaryUploadedFile) {
                                        $path = $first->getRealPath() ?: $first->getPathname();
                                    } elseif (is_string($first)) {
                                        $path = $first;
                                    }
                                }

                                if (! $path) {
                                    return;
                                }

                                /** @var NomorSuratExtractor $extractor */
                                $extractor = app(NomorSuratExtractor::class);

                                // ===== NOMOR SURAT =====
                                $nomor = $extractor->extract($path);
                                if (! empty($nomor)) {
                                    $set('nomor', $nomor);
                                }

                                // ===== HAL / PERIHAL → NAMA KEGIATAN =====
                                $perihal = $extractor->extractPerihal($path);
                                if (! empty($perihal)) {
                                    $set('nama_kegiatan', $perihal);
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
                            ->maxLength(500)
                            ->helperText('Diambil otomatis dari HAL/PERIHAL surat (bisa diubah).'),

                        DatePicker::make('tanggal')
                            ->label('Hari / Tanggal')
                            ->required()
                            ->displayFormat('d-m-Y'),

                        DateTimePicker::make('tindak_lanjut_deadline')
                            ->label('Batas Waktu Tindak Lanjut')
                            ->seconds(false)
                            ->native(false)
                            ->required(fn (Get $get) => $get('jenis_surat') === 'kegiatan_tindak_lanjut')
                            ->visible(fn (Get $get) => $get('jenis_surat') === 'kegiatan_tindak_lanjut')
                            ->helperText('Tanggal batas waktu tindak lanjut (TL) untuk surat kegiatan non-undangan.'),

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

                        // ⬇️ FIELD HIDDEN UNTUK STATUS DISPOSISI (0 / 1)
                        Hidden::make('sudah_disposisi')
                            ->default(0),

                        Toggle::make('tampilkan_di_public')
                            ->label('Tampilkan di dashboard publik')
                            ->helperText('Surat kegiatan dengan TL tidak akan ditampilkan di dashboard publik.')
                            ->live()
                            ->default(true)
                            ->disabled(fn (Get $get) => $get('jenis_surat') === 'kegiatan_tindak_lanjut')
                            ->afterStateHydrated(function ($state, callable $set, Get $get) {
                                if ($state === null) {
                                    $set('tampilkan_di_public', $get('jenis_surat') === 'undangan');
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                if ($get('jenis_surat') === 'kegiatan_tindak_lanjut') {
                                    $set('tampilkan_di_public', false);
                                }
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // =========================
                // SECTION: PERSONIL YANG MENGHADIRI (DI BAWAH)
                // =========================
                Section::make('Personil yang Menghadiri')
                    ->schema([
                        // ⬇️ TOGGLE: PILIH SEMUA PEGAWAI
                        Toggle::make('semua_personil')
                            ->label('Pilih semua pegawai')
                            ->helperText('Centang jika undangan melibatkan seluruh personil.')
                            ->reactive()
                            ->dehydrated(false) // tidak disimpan ke database
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    // jika toggle di-uncheck, biarkan user atur manual
                                    return;
                                }

                                // Ambil semua ID personil
                                $allIds = Personil::query()
                                    ->pluck('id')
                                    ->all();

                                $set('personils', $allIds);
                            }),

                        Select::make('personils')
                            ->label('Pilih Personil')
                            ->relationship('personils', 'nama')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // $state = array id personil atau null
                                if (is_array($state) && count($state) > 0) {
                                    // Ada personil -> sudah disposisi
                                    $set('sudah_disposisi', 1);
                                } else {
                                    // Tidak ada personil -> belum disposisi
                                    $set('sudah_disposisi', 0);
                                }
                            })
                            ->helperText('Pilih personil yang akan menghadiri kegiatan ini.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
