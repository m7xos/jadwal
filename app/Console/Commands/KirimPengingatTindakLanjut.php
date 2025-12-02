<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Services\WablasService;
use Illuminate\Console\Command;

class KirimPengingatTindakLanjut extends Command
{
    protected $signature = 'surat:ingatkan-tl';

    protected $description = 'Kirim pesan WA pengingat batas tindak lanjut surat masuk yang berupa kegiatan.';

    public function handle(WablasService $wablas): int
    {
        if (! $wablas->isConfigured()) {
            $this->error('Konfigurasi Wablas belum lengkap.');

            return self::FAILURE;
        }

        $now = now();

        $dueKegiatans = Kegiatan::query()
            ->where('jenis_surat', 'tindak_lanjut')
            ->whereNotNull('batas_tindak_lanjut')
            ->whereNull('tl_reminder_sent_at')
            ->where('batas_tindak_lanjut', '<=', $now)
            ->get();

        if ($dueKegiatans->isEmpty()) {
            $this->info('Tidak ada surat tindak lanjut yang mencapai batas waktu.');

            return self::SUCCESS;
        }

        foreach ($dueKegiatans as $kegiatan) {
            $success = $wablas->sendGroupTindakLanjutReminder($kegiatan);

            if ($success) {
                $kegiatan->forceFill(['tl_reminder_sent_at' => now()])->save();
                $this->info("Pengingat terkirim untuk surat: {$kegiatan->nomor} ({$kegiatan->nama_kegiatan})");
            } else {
                $this->error("Gagal mengirim pengingat untuk surat: {$kegiatan->nomor}");
            }
        }

        return self::SUCCESS;
    }
}
