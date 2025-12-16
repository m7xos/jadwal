<?php

namespace App\Filament\Widgets;

use App\Models\Kegiatan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class AgendaPerHariChart extends ChartWidget
{
    // NON-static
    protected ?string $heading = 'Jumlah Agenda per Hari (1 Bulan Terakhir)';

    protected ?string $maxHeight = '350px';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg'      => 2, // full width on desktop/right side
    ];

    protected function getData(): array
    {
        $today     = Carbon::today();
        $startDate = $today->copy()->subDays(29); // kurang lebih 1 bulan (30 hari)

        $rows = Kegiatan::query()
            ->selectRaw('DATE(tanggal) as date, COUNT(*) as total')
            ->whereDate('tanggal', '>=', $startDate)
            ->whereDate('tanggal', '<=', $today)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date'); // 'YYYY-MM-DD' => row

        $labels = [];
        $data   = [];

        $period = CarbonPeriod::create($startDate, $today);

        foreach ($period as $date) {
            /** @var Carbon $date */
            $key = $date->toDateString(); // 'YYYY-MM-DD'

            $labels[] = $date->locale('id')->isoFormat('D MMM'); // misal: 25 Nov
            $data[]   = (int) ($rows[$key]->total ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Agenda',
                    'data'  => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }
}
