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

        $now = Carbon::now();
        $sent = 0;
        $processedIds = [];

        // Pengingat awal (5 jam sebelum batas TL)
        $firstBatch = Kegiatan::query()
            ->where('jenis_surat', 'tindak_lanjut')
            ->whereNotNull('batas_tindak_lanjut')
            ->whereNull('tl_reminder_sent_at')
            ->whereNull('tindak_lanjut_selesai_at')
            ->where('batas_tindak_lanjut', '<=', $now->copy()->addHours(5))
            ->get();

        foreach ($firstBatch as $kegiatan) {
            $processedIds[] = $kegiatan->id;

            $result = $wablas->sendGroupTindakLanjutReminder($kegiatan);
            $success = (bool) ($result['success'] ?? false);

            $log = TindakLanjutReminderLog::firstOrNew([
                'kegiatan_id' => $kegiatan->id,
            ]);

            $log->status = $success ? 'success' : 'failed';
            $log->error_message = $result['error'] ?? null;
            $log->response = $result['response'] ?? null;
            $log->sent_at = $success ? Carbon::now() : $log->sent_at;
            $log->save();

            if ($success) {
                $kegiatan->update([
                    'tl_reminder_sent_at' => $log->sent_at ?? Carbon::now(),
                ]);
                $sent++;
                $this->info("Pengingat dikirim untuk kegiatan #{$kegiatan->id}");
            } else {
                Log::warning('Gagal mengirim pengingat TL WA untuk kegiatan.', [
                    'kegiatan_id' => $kegiatan->id,
                ]);
            }
        }

        // Pengingat terakhir tepat saat atau setelah batas TL jika belum selesai.
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
            $log->sent_at = $success ? Carbon::now() : $log->sent_at;
            $log->save();

            if ($success) {
                $kegiatan->update([
                    'tl_reminder_sent_at' => $kegiatan->tl_reminder_sent_at ?? $log->sent_at ?? Carbon::now(),
                    'tl_final_reminder_sent_at' => $log->sent_at ?? Carbon::now(),
                ]);
                $sent++;
                $this->info("Pengingat terakhir dikirim untuk kegiatan #{$kegiatan->id}");
            } else {
                Log::warning('Gagal mengirim pengingat terakhir TL WA untuk kegiatan.', [
                    'kegiatan_id' => $kegiatan->id,
                ]);
            }
        }

        $this->info("Total pengingat terkirim: {$sent}");

        // Jangan anggap error jika tidak ada yang perlu dikirim.
        return self::SUCCESS;
    }
}
