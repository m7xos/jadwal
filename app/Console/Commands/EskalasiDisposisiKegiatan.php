<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Services\MobileNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EskalasiDisposisiKegiatan extends Command
{
    protected $signature = 'kegiatan:escalate-disposisi';

    protected $description = 'Eskalasi notifikasi disposisi ke Sekcam jika tenggat terlewati.';

    public function handle(MobileNotificationService $notificationService): int
    {
        $kegiatans = Kegiatan::query()
            ->where('sudah_disposisi', false)
            ->whereNull('disposisi_escalated_at')
            ->where(function ($query) {
                $query->where('perlu_tindak_lanjut', true)
                    ->orWhereNotNull('surat_undangan');
            })
            ->get();

        $now = now();
        $count = 0;

        foreach ($kegiatans as $kegiatan) {
            $deadline = $this->resolveDeadline($kegiatan);
            if (! $deadline) {
                continue;
            }

            if ($deadline->greaterThan($now)) {
                continue;
            }

            $notificationService->notifyDisposisiEscalation($kegiatan);
            $count++;
        }

        $this->info("Eskalasi disposisi diproses: {$count}");

        return self::SUCCESS;
    }

    protected function resolveDeadline(Kegiatan $kegiatan): ?Carbon
    {
        if ($kegiatan->perlu_tindak_lanjut && $kegiatan->batas_tindak_lanjut) {
            return Carbon::parse($kegiatan->batas_tindak_lanjut);
        }

        if (! $kegiatan->surat_undangan || ! $kegiatan->tanggal) {
            return null;
        }

        $deadline = $kegiatan->tanggal->copy()->startOfDay();
        $time = $this->parseWaktu($kegiatan->waktu);

        if ($time) {
            return $deadline->setTime($time['hour'], $time['minute'], 0);
        }

        return $deadline->setTime(23, 59, 0);
    }

    /**
     * @return array{hour:int,minute:int}|null
     */
    protected function parseWaktu(?string $waktu): ?array
    {
        if (! $waktu) {
            return null;
        }

        if (! preg_match('/(\d{1,2})[.:](\d{2})/', $waktu, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return [
            'hour' => $hour,
            'minute' => $minute,
        ];
    }
}
