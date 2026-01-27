<?php

namespace App\Filament\Pages;

use App\Models\BanprovVerification;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use UnitEnum;

class VerifikasiBanprov extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Verifikasi Banprov';
    protected static string|UnitEnum|null $navigationGroup = 'Seksi Ekbang';
    protected static ?string $slug = 'verifikasi-banprov';
    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.verifikasi-banprov';

    /** @var array<int, array<string, mixed>> */
    public array $previewRows = [];
    public int $previewCount = 0;
    public ?string $previewTahap = null;
    public ?string $previewError = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_excel')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->mountUsing(function (Action $action, ?\Filament\Schemas\Schema $schema): void {
                    $this->resetPreview();
                    $schema?->fill([
                        'file' => null,
                        'tahap' => null,
                    ]);
                })
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->directory('import/banprov')
                        ->disk('public')
                        ->required()
                        ->helperText('Hanya data Kecamatan Watumalang yang akan diimport.')
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $this->loadPreviewFromFile($state, $set);
                        }),
                    TextInput::make('tahap')
                        ->label('Tahap Pencairan')
                        ->helperText('Diambil otomatis dari file, bisa disesuaikan jika perlu.')
                        ->required(),
                ])
                ->modalHeading('Import Verifikasi Banprov')
                ->modalDescription('Periksa preview sebelum menyimpan data.')
                ->modalWidth('6xl')
                ->modalContent(fn () => view('filament.pages.partials.verifikasi-banprov-preview', [
                    'previewRows' => $this->previewRows,
                    'previewCount' => $this->previewCount,
                    'previewTahap' => $this->previewTahap,
                    'previewError' => $this->previewError,
                ]))
                ->action(function (array $data): void {
                    $this->importFromState($data);
                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BanprovVerification::query())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tahap')
                    ->label('Tahap')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('kecamatan')
                    ->label('Kecamatan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('desa')
                    ->label('Desa')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('no_dpa')
                    ->label('No DPA')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenis_kegiatan')
                    ->label('Jenis Kegiatan')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('jumlah')
                    ->label('Jumlah (Rp)')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state, 0, ',', '.') : '-'),
                Tables\Columns\BadgeColumn::make('status_lpj')
                    ->label('LPJ')
                    ->formatStateUsing(fn ($state) => $state ? 'Sudah' : 'Belum')
                    ->colors([
                        'success' => true,
                        'gray' => false,
                    ]),
                Tables\Columns\BadgeColumn::make('status_monev')
                    ->label('Monev')
                    ->formatStateUsing(fn ($state) => $state ? 'Sudah' : 'Belum')
                    ->colors([
                        'success' => true,
                        'gray' => false,
                    ]),
                Tables\Columns\TextColumn::make('sumber_file')
                    ->label('Sumber File')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Import')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('print_verifikasi')
                    ->label('Cetak Lembar Verifikasi')
                    ->icon('heroicon-o-printer')
                    ->url(fn (BanprovVerification $record) => route('banprov.verifikasi.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('toggle_lpj')
                    ->label(fn (BanprovVerification $record) => $record->status_lpj ? 'Belum LPJ' : 'Sudah LPJ')
                    ->icon(fn (BanprovVerification $record) => $record->status_lpj ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (BanprovVerification $record) => $record->status_lpj ? 'gray' : 'success')
                    ->action(function (BanprovVerification $record): void {
                        $record->update(['status_lpj' => ! $record->status_lpj]);
                    }),
                Tables\Actions\Action::make('toggle_monev')
                    ->label(fn (BanprovVerification $record) => $record->status_monev ? 'Belum Monev' : 'Sudah Monev')
                    ->icon(fn (BanprovVerification $record) => $record->status_monev ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (BanprovVerification $record) => $record->status_monev ? 'gray' : 'success')
                    ->action(function (BanprovVerification $record): void {
                        $record->update(['status_monev' => ! $record->status_monev]);
                    }),
            ])
            ->emptyStateHeading('Belum ada data verifikasi banprov')
            ->emptyStateDescription('Gunakan tombol Import Excel untuk menambahkan data.');
    }

    protected function importFromState(array $state): void
    {
        $file = $state['file'] ?? null;
        $tahap = trim((string) ($state['tahap'] ?? ''));

        if (! $file) {
            Notification::make()
                ->title('File belum dipilih')
                ->danger()
                ->send();
            return;
        }

        if ($tahap === '') {
            Notification::make()
                ->title('Tahap pencairan belum diisi')
                ->danger()
                ->send();
            return;
        }

        $path = Storage::disk('public')->path($file);
        if (! is_file($path)) {
            Notification::make()
                ->title('File tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        try {
            $result = $this->extractBanprovRows($path);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal membaca file')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $rows = $result['rows'] ?? [];
        $imported = 0;
        $sourceName = basename($file);

        foreach ($rows as $row) {
            $payload = [
                'tahap' => $tahap,
                'kecamatan' => $row['kecamatan'],
                'desa' => $row['desa'],
                'no_dpa' => $row['no_dpa'],
                'jenis_kegiatan' => $row['jenis_kegiatan'],
                'jumlah' => $row['jumlah'],
                'sumber_file' => $sourceName,
            ];

            BanprovVerification::updateOrCreate(
                [
                    'tahap' => $tahap,
                    'kecamatan' => $row['kecamatan'],
                    'desa' => $row['desa'],
                    'no_dpa' => $row['no_dpa'],
                ],
                $payload
            );

            $imported++;
        }

        Notification::make()
            ->title('Import selesai')
            ->body("Total data diimport: {$imported}")
            ->success()
            ->send();

        $this->resetPreview();
    }

    protected function loadPreviewFromFile(?string $file, Set $set): void
    {
        $this->previewRows = [];
        $this->previewCount = 0;
        $this->previewTahap = null;
        $this->previewError = null;

        if (! $file) {
            return;
        }

        $path = Storage::disk('public')->path($file);
        if (! is_file($path)) {
            $this->previewError = 'File tidak ditemukan.';
            return;
        }

        try {
            $result = $this->extractBanprovRows($path);
        } catch (\Throwable $e) {
            $this->previewError = $e->getMessage();
            return;
        }

        $rows = $result['rows'] ?? [];
        $this->previewRows = array_slice($rows, 0, 20);
        $this->previewCount = count($rows);
        $this->previewTahap = $result['tahap'] ?? null;

        if ($this->previewTahap) {
            $set('tahap', $this->previewTahap);
        }
    }

    protected function resetPreview(): void
    {
        $this->previewRows = [];
        $this->previewCount = 0;
        $this->previewTahap = null;
        $this->previewError = null;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, tahap: string|null}
     */
    protected function extractBanprovRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $tahap = $this->extractTahap($sheet, $highestRow, $highestColIndex);
        $headerRow = $this->findHeaderRow($sheet, $highestRow, $highestColIndex);

        if (! $headerRow) {
            throw new \RuntimeException('Header kolom tidak ditemukan di file.');
        }

        $columnMap = $this->mapColumns($sheet, $headerRow, $highestColIndex);

        $rows = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $kecamatan = $this->cellString($sheet, $columnMap['kecamatan'], $row);
            $desa = $this->cellString($sheet, $columnMap['desa'], $row);
            $noDpa = $this->cellString($sheet, $columnMap['no_dpa'], $row);
            $jenis = $this->cellString($sheet, $columnMap['jenis_kegiatan'], $row);
            $jumlah = $this->cellNumber($sheet, $columnMap['jumlah'], $row);

            if ($kecamatan === '' && $desa === '' && $noDpa === '' && $jenis === '') {
                continue;
            }

            if (strcasecmp($kecamatan, 'Watumalang') !== 0) {
                continue;
            }

            $rows[] = [
                'kecamatan' => $kecamatan,
                'desa' => $desa,
                'no_dpa' => $noDpa,
                'jenis_kegiatan' => $jenis,
                'jumlah' => $jumlah,
            ];
        }

        return [
            'rows' => $rows,
            'tahap' => $tahap,
        ];
    }

    protected function extractTahap($sheet, int $highestRow, int $highestColIndex): ?string
    {
        $limit = min($highestRow, 12);
        for ($row = 1; $row <= $limit; $row++) {
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $value = trim((string) $sheet->getCellByColumnAndRow($col, $row)->getValue());
                if ($value === '') {
                    continue;
                }

                if (preg_match('/tahap\\s*([0-9ivx]+)/i', $value, $matches)) {
                    return strtoupper($matches[1]);
                }
            }
        }

        return null;
    }

    protected function findHeaderRow($sheet, int $highestRow, int $highestColIndex): ?int
    {
        $limit = min($highestRow, 30);
        for ($row = 1; $row <= $limit; $row++) {
            $values = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $values[] = strtoupper(trim((string) $sheet->getCellByColumnAndRow($col, $row)->getValue()));
            }

            $line = implode(' ', $values);
            if (str_contains($line, 'KEC') && str_contains($line, 'DESA')) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{no: int, kecamatan: int, desa: int, no_dpa: int, jenis_kegiatan: int, jumlah: int}
     */
    protected function mapColumns($sheet, int $headerRow, int $highestColIndex): array
    {
        $map = [
            'no' => 1,
            'kecamatan' => 2,
            'desa' => 3,
            'no_dpa' => 4,
            'jenis_kegiatan' => 5,
            'jumlah' => 6,
        ];

        for ($col = 1; $col <= $highestColIndex; $col++) {
            $value = strtoupper(trim((string) $sheet->getCellByColumnAndRow($col, $headerRow)->getValue()));
            if ($value === '') {
                continue;
            }

            if (str_contains($value, 'NO') && ! str_contains($value, 'DPA')) {
                $map['no'] = $col;
            }
            if (str_contains($value, 'KEC')) {
                $map['kecamatan'] = $col;
            }
            if (str_contains($value, 'DESA')) {
                $map['desa'] = $col;
            }
            if (str_contains($value, 'DPA')) {
                $map['no_dpa'] = $col;
            }
            if (str_contains($value, 'JENIS')) {
                $map['jenis_kegiatan'] = $col;
            }
            if (str_contains($value, 'JUMLAH')) {
                $map['jumlah'] = $col;
            }
        }

        return $map;
    }

    protected function cellString($sheet, int $column, int $row): string
    {
        $value = $sheet->getCellByColumnAndRow($column, $row)->getValue();

        return trim((string) $value);
    }

    protected function cellNumber($sheet, int $column, int $row): ?int
    {
        $value = $sheet->getCellByColumnAndRow($column, $row)->getValue();

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user || ! RoleAccess::canSeeNav($user, 'filament.admin.pages.verifikasi-banprov')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $akronim = strtolower(trim((string) ($user->jabatan_akronim ?? '')));

        return $akronim === 'ekbang';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user || ! RoleAccess::canAccessRoute($user, 'filament.admin.pages.verifikasi-banprov')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $akronim = strtolower(trim((string) ($user->jabatan_akronim ?? '')));

        return $akronim === 'ekbang';
    }
}
