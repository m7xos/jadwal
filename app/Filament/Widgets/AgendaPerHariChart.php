<?php

namespace App\Filament\Widgets;

use App\Models\Kegiatan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class AgendaPerHariChart extends ChartWidget
{
    // DI VERSI FILAMENT KAMU: heading HARUS non-static
    protected ?string $heading = 'Jumlah Agenda per Hari (14 Hari Terakhir)';

    // Urutan tampil di dashboard (boleh static)
    protected static ?int $sort = 20;

    // Auto refresh tiap 60 detik (boleh static)
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $today     = Carbon::today();
        $startDate = $today->copy()->subDays(13); // total 14 hari (0..13)

        // Ambil data agregat dari database
        $rows = Kegiatan::query()
            ->selectRaw('DATE(tanggal) as date, COUNT(*) as total')
            ->whereDate('tanggal', '>=', $startDate)
            ->whereDate('tanggal', '<=', $today)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date'); // key: 'YYYY-MM-DD'

        $labels = [];
        $data   = [];

        // Pastikan semua hari dalam periode punya nilai (kalau tidak ada agenda, 0)
        $period = CarbonPeriod::create($startDate, $today);

        foreach ($period as $date) {
            /** @var Carbon $date */
            $key = $date->toDateString(); // 'YYYY-MM-DD'

            $labels[] = $date->locale('id')->isoFormat('D MMM'); // misal: 1 Jan, 2 Jan
            $data[]   = (int) ($rows[$key]->total ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Agenda',
                    'data'  => $data,
                    // warna default Chart.js / Filament
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // bisa diganti 'line' kalau mau
    }
}
