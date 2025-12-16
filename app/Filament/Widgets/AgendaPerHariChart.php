<?php

namespace App\Filament\Widgets;

use App\Models\Kegiatan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class AgendaPerHariChart extends ChartWidget
{
    // NON-static
    protected ?string $heading = 'Jumlah Agenda Bulan Berjalan';

    protected ?string $maxHeight = '450px';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg'      => 2, // full width on desktop/right side
    ];

    private ?array $monthlyStats = null;

    protected function getData(): array
    {
        $stats = $this->getMonthlyStats();

        return [
            'datasets' => [
                [
                    'label' => 'Agenda Bulan Ini',
                    'data'  => $stats['data'],
                ],
            ],
            'labels' => $stats['labels'],
        ];
    }

    public function getDescription(): ?string
    {
        $stats = $this->getMonthlyStats();

        return "Bulan ini: {$stats['total_current']} agenda | Bulan lalu: {$stats['total_prev']} agenda";
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }

    private function getMonthlyStats(): array
    {
        if ($this->monthlyStats !== null) {
            return $this->monthlyStats;
        }

        $today        = Carbon::today();
        $startCurrent = $today->copy()->startOfMonth();
        $startPrev    = $startCurrent->copy()->subMonth();
        $endPrev      = $startCurrent->copy()->subDay();

        $rows = Kegiatan::query()
            ->selectRaw('DATE(tanggal) as date, COUNT(*) as total')
            ->whereDate('tanggal', '>=', $startCurrent)
            ->whereDate('tanggal', '<=', $today)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date'); // 'YYYY-MM-DD' => row

        $labels = [];
        $data   = [];

        $period = CarbonPeriod::create($startCurrent, $today);

        foreach ($period as $date) {
            /** @var Carbon $date */
            $key = $date->toDateString(); // 'YYYY-MM-DD'

            $labels[] = $date->locale('id')->isoFormat('D MMM'); // misal: 25 Nov
            $data[]   = (int) ($rows[$key]->total ?? 0);
        }

        $totalCurrent = array_sum($data);

        $totalPrev = Kegiatan::query()
            ->whereDate('tanggal', '>=', $startPrev)
            ->whereDate('tanggal', '<=', $endPrev)
            ->count();

        return $this->monthlyStats = [
            'labels'        => $labels,
            'data'          => $data,
            'total_current' => $totalCurrent,
            'total_prev'    => $totalPrev,
        ];
    }
}
