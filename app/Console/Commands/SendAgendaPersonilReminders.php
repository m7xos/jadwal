<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Services\WaGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendAgendaPersonilReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kegiatan:remind-personil {--offset=0 : Offset hari dari tanggal kegiatan (0 = hari ini, 1 = besok)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat agenda ke personil yang mendapatkan disposisi.';

    public function handle(): int
    {
        /** @var WaGatewayService $waGateway */
        $waGateway = app(WaGatewayService::class);

        if (! $waGateway->isConfigured()) {
            $this->warn('WA Gateway belum dikonfigurasi.');

            return self::FAILURE;
        }

        $offset = (int) $this->option('offset');
        $targetDate = Carbon::today()->addDays($offset);

        $items = Kegiatan::query()
            ->whereDate('tanggal', $targetDate->toDateString())
            ->where('sudah_disposisi', true)
            ->whereHas('personils')
            ->with('personils')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Tidak ada kegiatan yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($items as $kegiatan) {
            $success = $waGateway->sendToPersonils($kegiatan);

            if ($success) {
                $sent++;
                continue;
            }

            Log::warning('Pengingat agenda personil gagal dikirim.', [
                'kegiatan_id' => $kegiatan->id,
            ]);
        }

        $this->info("Total pengingat terkirim: {$sent}");

        return self::SUCCESS;
    }
}
