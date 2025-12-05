<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Models\TindakLanjutReminderLog;
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
        $deadlineThreshold = $now->copy()->addHours(5);
        $processedIds = [];
        $sent = 0;

        // Pengingat awal (5 jam sebelum batas TL)
        $dueKegiatans = Kegiatan::query()
            ->where('jenis_surat', 'tindak_lanjut')
            ->whereNotNull('batas_tindak_lanjut')
            ->whereNull('tl_reminder_sent_at')
            ->whereNull('tindak_lanjut_selesai_at')
            ->where('batas_tindak_lanjut', '<=', $deadlineThreshold)
            ->get();

        foreach ($dueKegiatans as $kegiatan) {
            $processedIds[] = $kegiatan->id;

            $result = $wablas->sendGroupTindakLanjutReminder($kegiatan);
            $success = (bool) ($result['success'] ?? false);

            $log = TindakLanjutReminderLog::firstOrNew([
                'kegiatan_id' => $kegiatan->id,
            ]);

            $log->status = $success ? 'success' : 'failed';
            $log->error_message = $result['error'] ?? null;
            $log->response = $result['response'] ?? null;
            $log->sent_at = $success ? now() : null;
            $log->save();

            if ($success) {
                $kegiatan->forceFill(['tl_reminder_sent_at' => $log->sent_at ?? now()])->save();
                $sent++;
                $this->info("Pengingat terkirim untuk surat: {$kegiatan->nomor} ({$kegiatan->nama_kegiatan})");
            } else {
                $this->error("Gagal mengirim pengingat untuk surat: {$kegiatan->nomor}");
            }
        }

        // Pengingat terakhir saat atau setelah batas TL jika belum selesai.
        $finalBatch = Kegiatan::query()
            ->where('jenis_surat', 'tindak_lanjut')
            ->whereNotNull('batas_tindak_lanjut')
            ->whereNull('tindak_lanjut_selesai_at')
            ->whereNull('tl_final_reminder_sent_at')
            ->where('batas_tindak_lanjut', '<=', $now)
            ->whereNotIn('id', $processedIds)
            ->get();

        foreach ($finalBatch as $kegiatan) {
            $result = $wablas->sendGroupTindakLanjutReminder($kegiatan);
            $success = (bool) ($result['success'] ?? false);

            $log = TindakLanjutReminderLog::firstOrNew([
                'kegiatan_id' => $kegiatan->id,
            ]);

            $log->status = $success ? 'success' : 'failed';
            $log->error_message = $result['error'] ?? null;
            $log->response = $result['response'] ?? null;
            $log->sent_at = $success ? now() : $log->sent_at;
            $log->save();

            if ($success) {
                $kegiatan->forceFill([
                    'tl_reminder_sent_at' => $kegiatan->tl_reminder_sent_at ?? $log->sent_at ?? now(),
                    'tl_final_reminder_sent_at' => $log->sent_at ?? now(),
                ])->save();
                $sent++;
                $this->info("Pengingat terakhir terkirim untuk surat: {$kegiatan->nomor} ({$kegiatan->nama_kegiatan})");
            } else {
                $this->error("Gagal mengirim pengingat terakhir untuk surat: {$kegiatan->nomor}");
            }
        }

        if ($sent === 0) {
            $this->info('Tidak ada surat tindak lanjut yang perlu dikirimkan pengingat.');
        }

        return self::SUCCESS;
    }
}
