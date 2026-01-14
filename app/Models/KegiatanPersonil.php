<?php

namespace App\Models;

use App\Services\WaGatewayService;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;

class KegiatanPersonil extends Pivot
{
    protected $table = 'kegiatan_personil';
    public $timestamps = true;

    protected static function booted(): void
    {
        static::created(function (KegiatanPersonil $pivot): void {
            $kegiatan = Kegiatan::find($pivot->kegiatan_id);
            $personil = Personil::find($pivot->personil_id);

            if (! $kegiatan || ! $personil) {
                return;
            }

            $noWa = trim((string) ($personil->no_wa ?? ''));
            if ($noWa === '') {
                return;
            }

            try {
                $waGateway = app(WaGatewayService::class);
                $waGateway->sendDisposisiNotification($kegiatan, $personil);
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim notifikasi disposisi ke personil.', [
                    'kegiatan_id' => $pivot->kegiatan_id,
                    'personil_id' => $pivot->personil_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
