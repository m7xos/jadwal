<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
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
            ->where('jenis_surat', 'kegiatan_tindak_lanjut')
            ->where('tampilkan_di_public', false)
            ->whereNotNull('tindak_lanjut_deadline')
            ->whereNull('tindak_lanjut_reminder_sent_at')
            ->where('tindak_lanjut_deadline', '<=', Carbon::now())
            ->get();

        if ($kegiatans->isEmpty()) {
            $this->info('Tidak ada surat kegiatan yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($kegiatans as $kegiatan) {
            $success = $wablas->sendGroupTindakLanjutReminder($kegiatan);

            if ($success) {
                $kegiatan->update([
                    'tindak_lanjut_reminder_sent_at' => Carbon::now(),
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
