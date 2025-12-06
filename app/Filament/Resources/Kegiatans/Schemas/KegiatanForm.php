<?php

namespace App\Filament\Resources\Kegiatans\Schemas;

use App\Models\Kegiatan;
use App\Models\Personil;
use App\Services\NomorSuratExtractor;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
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
                                'undangan'      => 'Surat Undangan',
                                'tindak_lanjut' => 'Surat Masuk',
                            ])
                            ->default('undangan')
                            ->required()
                            ->native(false)
                            ->helperText('Pilih apakah surat undangan atau surat masuk dengan batas tindak lanjut.')
                            ->reactive(), // penting supaya visible()/required() ikut berubah

                        
                        FileUpload::make('surat_undangan')
                            ->label('Berkas Surat (PDF)')
                            ->disk('public')
                            ->directory('surat-undangan')
                            ->preserveFilenames()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['application/pdf'])
                            ->required(fn (Get $get) => $get('jenis_surat') === 'undangan')
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
                                    now()->format('Ymd_His') . '_' . $file->getClientOriginalName()
                            )
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $storedPath = static::storeUploadedSurat($state, $get('surat_undangan'));

                                if (! $storedPath) {
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

                                // ===== TANGGAL SURAT =====
                                $tanggalSurat = $extractor->extractTanggal($path);
                                if (! empty($tanggalSurat)) {
                                    $set('tanggal', $tanggalSurat);
                                }
                            }),

                        TextInput::make('nomor')
                            ->label('Nomor Surat')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Akan otomatis diisi dari PDF jika pola nomor surat dikenali.')
                            ->unique(table: Kegiatan::class, column: 'nomor', ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, Get $get, ?Kegiatan $record) {
                                $nomor = trim((string) $state);

                                if ($nomor === '') {
                                    return;
                                }

                                $existing = Kegiatan::query()
                                    ->where('nomor', $nomor)
                                    ->when(
                                        $record,
                                        fn ($query) => $query->where('id', '!=', $record->id)
                                    )
                                    ->first();

                                if (! $existing) {
                                    return;
                                }

                                Notification::make()
                                    ->title('Nomor surat duplikat')
                                    ->body(
                                        "Nomor surat {$nomor} sudah terdaftar untuk surat "
                                        . ($existing->nama_kegiatan ?? '-')
                                    )
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }),

                        TextInput::make('nama_kegiatan')
                            ->label('Nama Kegiatan')
                            ->required()
                            ->maxLength(500)
                            ->helperText('Diambil otomatis dari HAL/PERIHAL surat (bisa diubah).'),

                        DatePicker::make('tanggal')
                            ->label('Tanggal Surat')
                            ->required()
                            ->helperText('Akan otomatis diisi dari PDF jika pola tanggal surat dikenali.')
                            ->displayFormat('d-m-Y'),

                        TextInput::make('waktu')
                            ->label('Waktu')
                            ->placeholder('Contoh: 09.00 - 11.00 WIB')
                            ->required(fn (Get $get) => $get('jenis_surat') === 'undangan')
                            ->visible(fn (Get $get) => $get('jenis_surat') === 'undangan')
                            // opsional: supaya tidak ikut disimpan kalau hidden
                            ->dehydrated(fn (Get $get) => $get('jenis_surat') === 'undangan')
                            ->maxLength(100),

                        TextInput::make('tempat')
							->label('Tempat')
							->required(fn (Get $get) => $get('jenis_surat') === 'undangan')
							->visible(fn (Get $get) => $get('jenis_surat') === 'undangan')
							->dehydrated(fn (Get $get) => $get('jenis_surat') === 'undangan')
							->maxLength(255),

                        DateTimePicker::make('batas_tindak_lanjut')
                            ->label('Batas Waktu Tindak Lanjut')
                            ->seconds(false)
                            ->visible(fn (Get $get) => $get('jenis_surat') === 'tindak_lanjut')
                            ->required(fn (Get $get) => $get('jenis_surat') === 'tindak_lanjut')
                            ->helperText('Wajib diisi untuk surat masuk yang harus ditindaklanjuti.'),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3),

                        // FIELD HIDDEN UNTUK STATUS DISPOSISI (0 / 1)
                        Hidden::make('sudah_disposisi')
                            ->default(0),

                        Toggle::make('tampilkan_di_public')
                            ->label('Tampilkan di dashboard publik')
                            ->helperText('Pilih apakah surat ini akan ditampilkan di dashboard publik.')
                            ->live()
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // =========================
                // SECTION: PERSONIL YANG MENGHADIRI
                // =========================
                Section::make('Personil yang ditugaskan')
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

    protected static function storeUploadedSurat(string|TemporaryUploadedFile $state, ?string $currentPath = null): ?string
    {
        if ($state instanceof TemporaryUploadedFile) {
            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
            }

            $filename = now()->format('Ymd_His') . '_' . $state->getClientOriginalName();

            return $state->storeAs('surat-undangan', $filename, 'public');
        }

        return is_string($state) ? $state : null;
    }

    protected static function populateFieldsFromPdf(?string $storedPath, callable $set): void
    {
        if (! $storedPath) {
            return;
        }

        $absolutePath = Storage::disk('public')->path($storedPath);

        if (! file_exists($absolutePath)) {
            return;
        }

        /** @var NomorSuratExtractor $extractor */
        $extractor = app(NomorSuratExtractor::class);

        $nomor = $extractor->extract($absolutePath);
        if (! empty($nomor)) {
            $set('nomor', $nomor);
        }

        $perihal = $extractor->extractPerihal($absolutePath);
        if (! empty($perihal)) {
            $set('nama_kegiatan', $perihal);
        }

        $tanggalString = $extractor->extractTanggal($absolutePath);
        if (! empty($tanggalString)) {
            $parsed = static::parseTanggalString($tanggalString);

            if ($parsed) {
                $set('tanggal', $parsed->toDateString());
            }
        }
    }

    protected static function renderPreviewButton(?string $path): ?HtmlString
    {
        if (blank($path)) {
            return null;
        }

        $token = Crypt::encryptString($path);

        $url = URL::temporarySignedRoute(
            'kegiatan.surat.preview',
            now()->addMinutes(30),
            ['token' => $token],
        );

        return new HtmlString(
            view('filament.components.preview-surat-button', ['url' => $url])->render()
        );
    }

    protected static function parseTanggalString(string $text): ?Carbon
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $bulanMap = [
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];

        if (preg_match('/\b(\d{1,2})\s+(' . implode('|', array_keys($bulanMap)) . ')\s+(\d{2,4})\b/iu', $text, $matches)) {
            $day = (int) $matches[1];
            $month = $bulanMap[strtolower($matches[2])] ?? null;
            $year = (int) $matches[3];

            if ($month) {
                if ($year < 100) {
                    $year += 2000;
                }

                try {
                    return Carbon::createSafe($year, $month, $day);
                } catch (\Throwable $th) {
                    return null;
                }
            }
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/', $text, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            if ($year < 100) {
                $year += 2000;
            }

            try {
                return Carbon::createSafe($year, $month, $day);
            } catch (\Throwable $th) {
                return null;
            }
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable $th) {
            return null;
        }
    }
}
