<?php

namespace App\Filament\Pages;

use App\Models\SuratKeluar;
use App\Models\Personil;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use BackedEnum;
use UnitEnum;

class LaporanSuratKeluarBulanan extends Page
{
    // ✅ Tipe property mengikuti Filament v4 yang kamu pakai
    protected static BackedEnum|string|null $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Rekap Surat Keluar';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?int $navigationSort  = 11;
    protected static ?string $title = 'Rekap Surat Keluar';
    protected ?string $heading = '';

    // ⚠️ DI VERSION-MU, $view BUKAN static → jangan pakai "static"
    protected string $view = 'filament.pages.laporan-surat-keluar-bulanan';

    // ⬇️ INI YANG MEMBUAT HALAMAN TIDAK MUNCUL DI MENU
    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.laporan-surat-keluar-bulanan');
    }

    /**
     * Bulan rekap dalam format "YYYY-MM" (untuk input type="month").
     */
    public ?string $bulan = null;
    public ?string $tahun = null;
    public ?string $jenisRekap = 'bulanan';
    public bool $ttdSrikandi = false;

    /**
     * Data baris laporan untuk tabel.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    public ?string $judulLaporan = null;
    public ?string $bulanLabel   = null;
    public ?string $periodeLabel = null;
    public ?string $periodeCaption = null;
    public ?string $namaCamat    = null;

    // tanggal rekap (untuk teks "Wonosobo, …")
    public ?Carbon $rekapDate = null;

    public function mount(): void
    {
        $now            = now();
        $this->bulan    = $now->format('Y-m'); // default bulan berjalan
        $this->tahun    = $now->format('Y');
        $this->jenisRekap = 'bulanan';
        $this->rekapDate = $now;

        $this->loadData();
    }

    public function updatedBulan(): void
    {
        $this->loadData();
    }

    public function updatedTahun(): void
    {
        $this->loadData();
    }

    public function updatedJenisRekap(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        if ($this->jenisRekap === 'tahunan') {
            $year = is_numeric($this->tahun ?? '') ? (int) $this->tahun : null;

            if (! $year) {
                $this->rows = [];
                $this->judulLaporan = 'Laporan Rekap Surat Keluar Tahun - Kantor Kecamatan Watumalang';
                $this->periodeLabel = '-';
                $this->periodeCaption = 'Tahun';
                $this->bulanLabel = null;

                return;
            }

            $start = Carbon::createFromDate($year, 1, 1)->startOfDay();
            $end = (clone $start)->endOfYear()->endOfDay();
            $this->periodeLabel = (string) $year;
            $this->periodeCaption = 'Tahun';
        } else {
            if (empty($this->bulan) || ! str_contains($this->bulan, '-')) {
                $this->rows         = [];
                $this->judulLaporan = 'Laporan Rekap Surat Keluar Bulan - Kantor Kecamatan Watumalang';
                $this->bulanLabel   = '-';
                $this->periodeLabel = '-';
                $this->periodeCaption = 'Bulan';

                return;
            }

            [$year, $month] = explode('-', $this->bulan);

            $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfDay();
            $end   = (clone $start)->endOfMonth()->endOfDay();
            $this->periodeLabel = $start->locale('id')->isoFormat('MMMM Y');
            $this->periodeCaption = 'Bulan';
        }

        /** @var Collection<int, SuratKeluar> $suratKeluars */
        $suratKeluars = SuratKeluar::with(['kodeSurat', 'requester'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('tanggal_surat', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($subQuery) use ($start, $end) {
                        $subQuery->where('status', SuratKeluar::STATUS_BOOKED)
                            ->whereBetween('booked_at', [$start->toDateString(), $end->toDateString()]);
                    });
            })
            ->orderBy('tanggal_surat')
            ->orderBy('booked_at')
            ->orderBy('created_at')
            ->get();

        $this->rows = $suratKeluars->map(function (SuratKeluar $surat, int $index) {
            $statusLabel = $surat->status === SuratKeluar::STATUS_BOOKED ? 'Nomor dibooking' : 'Terbit';
            $perihal = $surat->perihal === SuratKeluar::BOOKED_PLACEHOLDER ? '-' : ($surat->perihal ?? '-');
            $pemohon = $surat->requester?->nama ?? $surat->requested_by_number ?? '-';

            return [
                'no'             => $index + 1,
                'nomor_surat'    => $surat->nomor_label ?? '-',
                'tanggal_surat'  => optional($surat->tanggal_surat)->format('d-m-Y') ?? '-',
                'status'         => $statusLabel,
                'perihal'        => $perihal,
                'kode'           => $surat->kodeSurat?->kode ?? '-',
                'pemohon'        => $pemohon,
                'sumber'         => $surat->source ?? '-',
            ];
        })->all();

        // Contoh: "November 2025"
        $this->bulanLabel = $this->jenisRekap === 'tahunan'
            ? null
            : $start->locale('id')->isoFormat('MMMM Y');

        // Judul sesuai permintaan:
        $periodeLabel = $this->periodeLabel ?? $this->bulanLabel ?? '-';
        $this->judulLaporan = 'Laporan Rekap Surat Keluar ' .
            ($this->periodeCaption ?? 'Bulan') . ' ' .
            $periodeLabel .
            ' Kantor Kecamatan Watumalang';

        // Ambil nama camat dari Personil (jabatan LIKE %Camat Watumalang%)
        $this->namaCamat = Personil::query()
            ->where('jabatan', 'like', '%Camat Watumalang%')
            ->value('nama');

        // tanggal rekap (kalau mau beda bisa diubah di sini)
        $this->rekapDate = now();
    }

    protected function getViewData(): array
    {
        return [
            'rows'         => $this->rows,
            'judulLaporan' => $this->judulLaporan,
            'bulanLabel'   => $this->bulanLabel,
            'periodeLabel' => $this->periodeLabel ?? $this->bulanLabel,
            'periodeCaption' => $this->periodeCaption,
            'namaCamat'    => $this->namaCamat,
            'rekapDate'    => $this->rekapDate ?? now(),
            'bulan'        => $this->bulan,
            'tahun'        => $this->tahun,
            'jenisRekap'   => $this->jenisRekap,
            'ttdSrikandi'  => $this->ttdSrikandi,
        ];
    }
}
