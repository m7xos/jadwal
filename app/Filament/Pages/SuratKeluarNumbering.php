<?php

namespace App\Filament\Pages;

use App\Models\KodeSurat;
use App\Services\SuratKeluarService;
use App\Support\RoleAccess;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class SuratKeluarNumbering extends Page implements HasForms
{
    use InteractsWithForms;

    private const ALL_CODES = 'all';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $title = 'Status Nomor Surat Keluar';
    protected static ?string $navigationLabel = 'Status Nomor Surat';
    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Surat';
    protected static ?string $slug = 'surat-keluar-status';
    protected static ?int $navigationSort = 16;

    protected string $view = 'filament.pages.surat-keluar-numbering';

    public ?array $data = [];

    public string $availableList = '-';
    public string $bookedList = '-';

    public function mount(): void
    {
        $kodeId = KodeSurat::query()->orderBy('kode')->value('id');

        $this->form->fill([
            'kode_surat_id' => $kodeId,
            'tahun' => now()->year,
        ]);

        $this->refreshLists();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter')
                    ->schema([
                        Select::make('kode_surat_id')
                            ->label('Kode Klasifikasi')
                            ->options(function () {
                                $options = [
                                    self::ALL_CODES => 'Semua Kode Klasifikasi',
                                ];

                                foreach (KodeSurat::query()->orderBy('kode')->get() as $kode) {
                                    $options[$kode->id] = $kode->kode . ' - ' . $kode->keterangan;
                                }

                                return $options;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshLists()),

                        TextInput::make('tahun')
                            ->label('Tahun')
                            ->numeric()
                            ->required()
                            ->default(now()->year)
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshLists()),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function refreshLists(): void
    {
        $state = $this->form->getState();
        $kodeId = $state['kode_surat_id'] ?? null;
        $tahun = (int) ($state['tahun'] ?? now()->year);

        if (! $kodeId) {
            $this->availableList = '-';
            $this->bookedList = '-';
            return;
        }

        if ($kodeId === self::ALL_CODES) {
            $this->refreshAllCodes($tahun);
            return;
        }

        $kode = KodeSurat::find($kodeId);
        if (! $kode) {
            $this->availableList = '-';
            $this->bookedList = '-';
            return;
        }

        /** @var SuratKeluarService $service */
        $service = app(SuratKeluarService::class);
        $status = $service->getNumberingStatus($kode, $tahun);

        $this->availableList = $this->formatAvailableList($kode->kode, $status['available']);
        $this->bookedList = $this->formatBookedList($kode->kode, $status['booked']);
    }

    protected function refreshAllCodes(int $tahun): void
    {
        $kodeSurats = KodeSurat::query()->orderBy('kode')->get();
        if ($kodeSurats->isEmpty()) {
            $this->availableList = '-';
            $this->bookedList = '-';
            return;
        }

        /** @var SuratKeluarService $service */
        $service = app(SuratKeluarService::class);

        $availableLines = [];
        $bookedLines = [];

        foreach ($kodeSurats as $kode) {
            $status = $service->getNumberingStatus($kode, $tahun);

            foreach ($status['available'] as $number) {
                $availableLines[] = $kode->kode . '/' . $number . ' - nomor surat tersedia';
            }

            foreach ($status['booked'] as $row) {
                $label = $kode->kode . '/' . $row['nomor'] . ' - nomor sudah dibooking';
                if (! empty($row['booked_at'])) {
                    $label .= ' (' . $row['booked_at'] . ')';
                }
                $bookedLines[] = $label;
            }
        }

        $this->availableList = $availableLines
            ? implode("\n", $availableLines)
            : 'Tidak ada nomor kosong.';
        $this->bookedList = $bookedLines
            ? implode("\n", $bookedLines)
            : 'Tidak ada nomor dibooking.';
    }

    /**
     * @param array<int, int> $numbers
     */
    protected function formatAvailableList(string $kode, array $numbers): string
    {
        if (empty($numbers)) {
            return 'Tidak ada nomor kosong.';
        }

        $lines = [];
        foreach ($numbers as $number) {
            $lines[] = $kode . '/' . $number . ' - nomor surat tersedia';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{nomor: int, booked_at: string|null}> $rows
     */
    protected function formatBookedList(string $kode, array $rows): string
    {
        if (empty($rows)) {
            return 'Tidak ada nomor dibooking.';
        }

        $lines = [];
        foreach ($rows as $row) {
            $label = $kode . '/' . $row['nomor'] . ' - nomor sudah dibooking';
            if (! empty($row['booked_at'])) {
                $label .= ' (' . $row['booked_at'] . ')';
            }
            $lines[] = $label;
        }

        return implode("\n", $lines);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.surat-keluar-status');
    }
}
