<?php

namespace App\Filament\Widgets;

use App\Models\Kegiatan;
use App\Models\Personil;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Services\WaGatewayService;

class AgendaStatsOverview extends BaseWidget
{
    // Judul widget di dashboard
    protected ?string $heading = 'Ringkasan Agenda Kegiatan';

    /**
     * Filament v4: gunakan getStats() dan Stat::make()
     */
    protected function getStats(): array
    {
        $today       = Carbon::today();
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek   = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $in7Days     = $today->copy()->addDays(7);

        $totalAgenda          = Kegiatan::count();
        $agendaHariIni        = Kegiatan::whereDate('tanggal', $today)->count();
        $agendaMingguIni      = Kegiatan::whereBetween('tanggal', [$startOfWeek, $endOfWeek])->count();
        $agenda7HariKeDepan   = Kegiatan::whereBetween('tanggal', [$today, $in7Days])->count();
        $agendaBelumDisposisi = Kegiatan::where('sudah_disposisi', false)->count();
        $totalPersonil        = Personil::count();

        $gatewayStat = $this->buildGatewayStat();

        return [
            Stat::make('Agenda Hari Ini', $agendaHariIni)
                ->description('Kegiatan pada hari ini')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color($agendaHariIni > 0 ? 'success' : 'gray'),

            Stat::make('Belum Disposisi', $agendaBelumDisposisi)
                ->description('Menunggu disposisi pimpinan')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($agendaBelumDisposisi > 0 ? 'warning' : 'success'),

            Stat::make('Agenda 7 Hari ke Depan', $agenda7HariKeDepan)
                ->description('Termasuk hari ini')
                ->descriptionIcon('heroicon-o-forward')
                ->color($agenda7HariKeDepan > 0 ? 'info' : 'gray'),

            Stat::make('Total Agenda', $totalAgenda)
                ->description('Semua agenda terdata')
                ->descriptionIcon('heroicon-o-archive-box')
                ->color('gray'),

            Stat::make('Total Personil', $totalPersonil)
                ->description('Personil yang terdaftar')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('gray'),

            $gatewayStat,
        ];
    }

    /**
     * Auto refresh (polling) – cara aman untuk Filament v4
     */
    protected function getPollingInterval(): ?string
    {
        return '30s'; // refresh tiap 30 detik
    }

    protected function buildGatewayStat(): Stat
    {
        try {
            /** @var WaGatewayService $service */
            $service = app(WaGatewayService::class);
            $status = $service->getDeviceStatus();
        } catch (\Throwable $e) {
            $status = ['success' => false, 'error' => $e->getMessage()];
        }

        $ok = $status['success'] ?? false;
        $state = strtolower($status['status'] ?? 'unknown');
        $label = $ok ? ucfirst($state) : 'Tidak tersedia';

        $color = match ($state) {
            'online', 'connected' => 'success',
            'connecting' => 'warning',
            'offline', 'disconnected' => 'danger',
            default => $ok ? 'gray' : 'danger',
        };

        $value = match ($state) {
            'online', 'connected' => '🟢 Online',
            'offline', 'disconnected' => '🔴 Offline',
            'connecting' => '🟡 Connecting',
            default => '⚪ ' . $label,
        };

        return Stat::make('WA Gateway', $value)
            ->description(null)
            ->color($color);
    }
}
