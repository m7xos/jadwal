<?php

namespace App\Filament\Resources\Kegiatans\Schemas;

use App\Models\Personil;
use App\Services\NomorSuratExtractor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                        Select::make('jenis_surat')
                            ->label('Jenis Surat')
                            ->options([
                                'undangan'        => 'Surat Undangan (tampil di publik)',
                                'tindak_lanjut'   => 'Surat Masuk (ada batas tindak lanjut)',
                                'kegiatan_tindak_lanjut' => 'Kegiatan Tindak Lanjut',
                            ])
                            ->default('undangan')
                            ->required()
                            ->native(false)
                            ->helperText('Pilih apakah surat undangan atau surat masuk dengan batas tindak lanjut.')
                            ->reactive(),

                        DateTimePicker::make('batas_tindak_lanjut')
                            ->label('Batas Waktu Tindak Lanjut')
                            ->seconds(false)
                            // ⬇️ PAKAI Get, BUKAN SchemaGet
                            ->visible(fn (Get $get) => $get('jenis_surat') === 'tindak_lanjut')
                            ->required(fn (Get $get) => $get('jenis_surat') === 'tindak_lanjut')
                            ->helperText('Wajib diisi untuk surat masuk yang harus ditindaklanjuti.'),

                        FileUpload::make('surat_undangan')
                            ->label('Berkas Surat (PDF)')
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

                        // FIELD HIDDEN UNTUK STATUS DISPOSISI (0 / 1)
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
                // SECTION: PERSONIL YANG MENGHADIRI
                // =========================
                Section::make('Personil yang Menghadiri')
                    ->schema([
                        Toggle::make('semua_personil')
                            ->label('Pilih semua pegawai')
                            ->helperText('Centang jika undangan melibatkan seluruh personil.')
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }

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
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (is_array($state) && count($state) > 0) {
                                    $set('sudah_disposisi', 1);
                                } else {
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
