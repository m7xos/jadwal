<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Services\WaGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendDisposisiHMinusOneReminder extends Command
{
    protected $signature = 'kegiatan:remind-disposisi-h-1';

    protected $description = 'Kirim pengingat Mohon Disposisi H-1 ke grup WA default.';

    public function handle(): int
    {
        /** @var WaGatewayService $waGateway */
        $waGateway = app(WaGatewayService::class);

        if (! $waGateway->isConfigured()) {
            $this->warn('WA Gateway belum dikonfigurasi.');

            return self::FAILURE;
        }

        $targetDate = Carbon::today()->addDay();

        $items = Kegiatan::query()
            ->whereDate('tanggal', $targetDate->toDateString())
            ->where('sudah_disposisi', false)
            ->orderBy('tanggal')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Tidak ada agenda H-1 yang menunggu disposisi.');

            return self::SUCCESS;
        }

        $items->loadMissing('personils');

        $success = $waGateway->sendGroupBelumDisposisi($items);

        if ($success) {
            $this->info("Pengingat Mohon Disposisi H-1 terkirim: {$items->count()} agenda.");

            return self::SUCCESS;
        }

        Log::warning('Gagal mengirim pengingat Mohon Disposisi H-1.', [
            'tanggal' => $targetDate->toDateString(),
            'count' => $items->count(),
        ]);

        $this->warn('Gagal mengirim pengingat Mohon Disposisi H-1.');

        return self::SUCCESS;
    }
}
