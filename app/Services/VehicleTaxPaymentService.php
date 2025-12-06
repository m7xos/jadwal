<?php

namespace App\Services;

use App\Models\VehicleTax;
use Illuminate\Support\Carbon;

class VehicleTaxPaymentService
{
    public function markPaidByPlat(string $platNomor): ?VehicleTax
    {
        $normalized = strtoupper(str_replace(' ', '', $platNomor));

        $vehicle = VehicleTax::query()
            ->whereRaw('REPLACE(plat_nomor, " ", "") = ?', [$normalized])
            ->first();

        if (! $vehicle) {
            return null;
        }

        $vehicle->forceFill([
            'status_pajak' => 'lunas',
            'pajak_lunas_at' => Carbon::now(),
        ])->save();

        return $vehicle;
    }
}
