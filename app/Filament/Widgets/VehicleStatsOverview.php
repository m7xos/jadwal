<?php

namespace App\Filament\Widgets;

use App\Models\VehicleAsset;
use App\Models\VehicleTax;
use App\Support\RoleAccess;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VehicleStatsOverview extends BaseWidget
{
    protected ?string $heading = 'Ringkasan Kendaraan';

    /**
     * Tampilkan hanya bila pengguna memiliki salah satu hak akses kendaraan.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        $identifiers = [
            'filament.admin.resources.pajak-kendaraan',
            'filament.admin.resources.vehicle-assets',
            'filament.admin.resources.vehicle-tax-reminder-logs',
        ];

        foreach ($identifiers as $identifier) {
            if (RoleAccess::canSeeNav($user, $identifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalAssets = VehicleAsset::count();
        $totalTax    = VehicleTax::count();

        $assignedCount = VehicleTax::query()
            ->whereNotNull('personil_id')
            ->count();

        $paidCount = VehicleTax::query()
            ->where('status_pajak', 'lunas')
            ->count();

        $paidPercentage = $totalTax > 0
            ? round(($paidCount / $totalTax) * 100, 1)
            : 0.0;

        $paidColor = match (true) {
            $paidPercentage >= 80 => 'success',
            $paidPercentage >= 50 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Data Kendaraan', $totalAssets)
                ->description('Total kendaraan tercatat')
                ->descriptionIcon('heroicon-o-truck'),

            Stat::make('Dipegang Personil', $assignedCount)
                ->description('Unit dengan penanggung jawab')
                ->descriptionIcon('heroicon-o-user'),

            Stat::make('Pajak Lunas', "{$paidPercentage}%")
                ->description(
                    $totalTax > 0
                        ? "{$paidCount} dari {$totalTax} kendaraan"
                        : 'Belum ada data pajak'
                )
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($paidColor),
        ];
    }
}
