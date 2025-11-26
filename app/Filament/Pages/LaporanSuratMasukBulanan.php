<?php

namespace App\Filament\Pages;

use App\Models\Kegiatan;
use App\Models\Personil;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;
use BackedEnum;

class LaporanSuratMasukBulanan extends Page
{
    // ✅ Tipe property mengikuti Filament v4 yang kamu pakai
    protected static BackedEnum|string|null $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Laporan Surat Masuk Bulanan';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?int $navigationSort  = 10;

    // ⚠️ DI VERSION-MU, $view BUKAN static → jangan pakai "static"
    protected string $view = 'filament.pages.laporan-surat-masuk-bulanan';

    // ⬇️ INI YANG MEMBUAT HALAMAN TIDAK MUNCUL DI MENU
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * Bulan rekap dalam format "YYYY-MM" (untuk input type="month").
     */
    public ?string $bulan = null;

    /**
     * Data baris laporan untuk tabel.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    public ?string $judulLaporan = null;
    public ?string $bulanLabel   = null;
    public ?string $namaCamat    = null;

    // tanggal rekap (untuk teks "Wonosobo, …")
    public ?Carbon $rekapDate = null;

    public function mount(): void
    {
        $now            = now();
        $this->bulan    = $now->format('Y-m'); // default bulan berjalan
        $this->rekapDate = $now;

        $this->loadData();
    }

    public function updatedBulan(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        if (empty($this->bulan) || ! str_contains($this->bulan, '-')) {
            $this->rows         = [];
            $this->judulLaporan = 'Laporan Rekap Surat Masuk Bulan - Kantor Kecamatan Watumalang';
            $this->bulanLabel   = '-';

            return;
        }

        [$year, $month] = explode('-', $this->bulan);

        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth()->endOfDay();

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
        $this->bulanLabel = $start->locale('id')->isoFormat('MMMM Y');

        // Judul sesuai permintaan:
        $this->judulLaporan = 'Laporan Rekap Surat Masuk Bulan ' .
            $this->bulanLabel .
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
            'namaCamat'    => $this->namaCamat,
            'rekapDate'    => $this->rekapDate ?? now(),
            'bulan'        => $this->bulan,
        ];
    }
}
