<?php

namespace App\Filament\Pages;

use App\Services\SuratKeluarService;
use App\Support\RoleAccess;
use BackedEnum;
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
        $this->form->fill([
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
                        TextInput::make('tahun')
                            ->label('Tahun')
                            ->numeric()
                            ->required()
                            ->default(now()->year)
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshLists()),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function refreshLists(): void
    {
        $state = $this->form->getState();
        $tahun = (int) ($state['tahun'] ?? now()->year);

        /** @var SuratKeluarService $service */
        $service = app(SuratKeluarService::class);
        $status = $service->getGlobalNumberingStatus($tahun);

        $this->availableList = $this->formatAvailableList($status['available']);
        $this->bookedList = $this->formatBookedList($status['booked']);
    }

    /**
     * @param array<int, int> $numbers
     */
    protected function formatAvailableList(array $numbers): string
    {
        if (empty($numbers)) {
            return 'Tidak ada nomor kosong.';
        }

        $lines = [];
        foreach ($numbers as $number) {
            $lines[] = $number . ' - nomor surat tersedia';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{nomor: int, booked_at: string|null, kode: string|null}> $rows
     */
    protected function formatBookedList(array $rows): string
    {
        if (empty($rows)) {
            return 'Tidak ada nomor dibooking.';
        }

        $lines = [];
        foreach ($rows as $row) {
            $kode = $row['kode'] ?: '-';
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
