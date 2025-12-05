<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Models\TindakLanjutReminderLog;
use App\Services\WablasService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RemindTindakLanjutCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kegiatan:remind-tindak-lanjut';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat WA untuk surat kegiatan yang mencapai batas waktu tindak lanjut.';

    public function handle(): int
    {
        /** @var WablasService $wablas */
        $wablas = app(WablasService::class);

        if (! $wablas->isConfigured()) {
            $this->warn('Wablas belum dikonfigurasi.');

            return self::FAILURE;
        }

        /** @var Collection<int, Kegiatan> $kegiatans */
        $kegiatans = Kegiatan::query()
            ->where('jenis_surat', 'tindak_lanjut')
            ->whereNotNull('batas_tindak_lanjut')
            ->whereNull('tl_reminder_sent_at')
            ->where('batas_tindak_lanjut', '<=', Carbon::now())
            ->get();

        if ($kegiatans->isEmpty()) {
            $this->info('Tidak ada surat kegiatan yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($kegiatans as $kegiatan) {
            $result = $wablas->sendGroupTindakLanjutReminder($kegiatan);
            $success = (bool) ($result['success'] ?? false);

            TindakLanjutReminderLog::create([
                'kegiatan_id' => $kegiatan->id,
                'status' => $success ? 'success' : 'failed',
                'error_message' => $result['error'] ?? null,
                'response' => $result['response'] ?? null,
                'sent_at' => $success ? Carbon::now() : null,
            ]);

            if ($success) {
                $kegiatan->update([
                    'tl_reminder_sent_at' => Carbon::now(),
                ]);
                $sent++;

                $this->info("Pengingat dikirim untuk kegiatan #{$kegiatan->id}");
            } else {
                Log::warning('Gagal mengirim pengingat TL WA untuk kegiatan.', [
                    'kegiatan_id' => $kegiatan->id,
                ]);
            }
        }

        $this->info("Total pengingat terkirim: {$sent}");

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}
