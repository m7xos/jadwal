<?php

namespace App\Filament\Pages;

use App\Models\Kegiatan;
use App\Models\Personil;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;
use BackedEnum;

class LaporanSuratMasukBulanan extends Page
{
    // ✅ Tipe property mengikuti Filament v4 yang kamu pakai
    protected static BackedEnum|string|null $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Rekap Kegiatan';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?int $navigationSort  = 10;
    protected static ?string $title = 'Rekap Kegiatan';
    protected ?string $heading = '';

    // ⚠️ DI VERSION-MU, $view BUKAN static → jangan pakai "static"
    protected string $view = 'filament.pages.laporan-surat-masuk-bulanan';

    // ⬇️ INI YANG MEMBUAT HALAMAN TIDAK MUNCUL DI MENU
    public static function shouldRegisterNavigation(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.pages.laporan-surat-masuk-bulanan');
    }

    /**
     * Bulan rekap dalam format "YYYY-MM" (untuk input type="month").
     */
    public ?string $bulan = null;
    public ?string $tahun = null;
    public ?string $jenisRekap = 'bulanan';

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
                $this->judulLaporan = 'Laporan Rekap Kegiatan Tahun - Kantor Kecamatan Watumalang';
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
                $this->judulLaporan = 'Laporan Rekap Kegiatan Bulan - Kantor Kecamatan Watumalang';
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

        /** @var Collection<int, Kegiatan> $kegiatans */
        $kegiatans = Kegiatan::with('personils')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->get();

        $this->rows = $kegiatans->map(function (Kegiatan $kegiatan, int $index) {
            return [
                'no'             => $index + 1,
                'nomor'          => $kegiatan->nomor ?? '-',
                'tanggal_surat'  => optional($kegiatan->tanggal)->format('d-m-Y') ?? '-',
                'waktu'          => $kegiatan->waktu ?? '-',
                'nama_kegiatan'  => $kegiatan->nama_kegiatan ?? '-',
                'tempat'         => $kegiatan->tempat ?? '-',
                'keterangan'     => $kegiatan->keterangan ?? '',
                'personil_disp'  => ($kegiatan->personils ?? collect())
                    ->map(function ($p) {
                        $jabatan = $p->jabatan ? ' (' . $p->jabatan . ')' : '';
                        return $p->nama . $jabatan;
                    })
                    ->implode('; '),
            ];
        })->all();

        // Contoh: "November 2025"
        $this->bulanLabel = $this->jenisRekap === 'tahunan'
            ? null
            : $start->locale('id')->isoFormat('MMMM Y');

        // Judul sesuai permintaan:
        $periodeLabel = $this->periodeLabel ?? $this->bulanLabel ?? '-';
        $this->judulLaporan = 'Laporan Rekap Kegiatan ' .
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
        ];
    }
}
