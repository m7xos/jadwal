<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Kegiatan;
use App\Models\PersonilCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScheduleResponder
{
    /**
     * Proses pesan grup WA untuk perintah jadwal.
     *
     * @return bool true jika pesan sudah ditangani (balasan dikirim / dicoba kirim)
     */
    public function handle(array $payload, WaGatewayService $waGateway): bool
    {
        if (! ($payload['isGroup'] ?? false)) {
            return false;
        }

        $text = $this->extractText($payload);
        if ($text === null) {
            return false;
        }

        $groupIdRaw = $this->extractGroupId($payload);
        if (! $groupIdRaw) {
            return false;
        }

        $command = $this->detectCommand($text);
        if ($command === null) {
            return false;
        }

        [$date, $onlyPending, $label] = $this->commandMeta($command);

        $groupCandidates = $this->candidateGroupIds($groupIdRaw);

        // Cari data dari tabel kegiatan (utama) dan fallback ke tabel schedules jika ada.
        $kegiatan = $this->getKegiatanForGroup($groupCandidates, $date, $onlyPending);
        $schedules = $kegiatan->isNotEmpty()
            ? collect()
            : $this->getSchedulesForGroup($groupCandidates, $date, $onlyPending);

        $message = $kegiatan->isNotEmpty()
            ? $this->formatKegiatanMessage($date, $label, $kegiatan, $onlyPending)
            : $this->formatScheduleMessage($date, $label, $schedules, $onlyPending);

        $sent = $waGateway->sendTextToSpecificGroup($groupIdRaw, $message);
        $sentOk = (bool) ($sent['success'] ?? false);

        Log::info('ScheduleResponder processed command', [
            'group'        => $groupIdRaw,
            'command'      => $command,
            'date'         => $date->toDateString(),
            'only_pending' => $onlyPending,
            'count_kegiatan' => $kegiatan->count(),
            'count_schedules' => $schedules->count(),
            'sent'         => $sentOk,
            'error'        => $sentOk ? null : ($sent['error'] ?? null),
        ]);

        return true;
    }

    protected function detectCommand(string $text): ?string
    {
        $text = mb_strtolower($text);

        $hasJadwal   = str_contains($text, 'jadwal');
        $hasKegiatan = str_contains($text, 'kegiatan');
        $hasHariIni  = str_contains($text, 'hari ini');
        $hasBesok    = str_contains($text, 'besok');
        $hasPending  = str_contains($text, 'belum disposisi');

        if (($hasJadwal || $hasKegiatan) && $hasPending && $hasHariIni) {
            return 'today_pending';
        }

        if (($hasJadwal || $hasKegiatan) && $hasPending && $hasBesok) {
            return 'tomorrow_pending';
        }

        // Jika minta "belum disposisi" tanpa sebut hari, default hari ini.
        if (($hasJadwal || $hasKegiatan) && $hasPending) {
            return 'today_pending';
        }

        if (($hasJadwal || $hasKegiatan) && $hasHariIni) {
            return 'today_all';
        }

        if (($hasJadwal || $hasKegiatan) && $hasBesok) {
            return 'tomorrow_all';
        }

        return null;
    }

    protected function commandMeta(string $command): array
    {
        return match ($command) {
            'today_pending'    => [Carbon::today(), true, 'Jadwal belum disposisi hari ini'],
            'tomorrow_pending' => [Carbon::today()->addDay(), true, 'Jadwal belum disposisi besok'],
            'today_all'        => [Carbon::today(), false, 'Jadwal kegiatan hari ini'],
            'tomorrow_all'     => [Carbon::today()->addDay(), false, 'Jadwal kegiatan besok'],
        };
    }

    protected function formatScheduleMessage(Carbon $date, string $label, $schedules, bool $onlyPending): string
    {
        $dateLabel = $this->formatDateIndo($date);

        if ($schedules->isEmpty()) {
            $noDataText = $onlyPending
                ? "Tidak ada surat yang belum disposisi pada {$dateLabel}."
                : "Belum ada jadwal pada {$dateLabel}.";

            return $noDataText;
        }

        $lines = [];
        foreach ($schedules as $idx => $schedule) {
            $time      = $schedule->starts_at ? $schedule->starts_at->format('H:i') : '-';
            $disposed  = $schedule->is_disposed ? 'Sudah disposisi' : 'Belum disposisi';
            $location  = $schedule->location ? " | Lokasi: {$schedule->location}" : '';
            $notes     = $schedule->notes ? "\n   Catatan: {$schedule->notes}" : '';

            $lines[] = sprintf(
                "%d. %s - %s%s\n   Status: %s%s",
                $idx + 1,
                $time,
                $schedule->title,
                $location,
                $disposed,
                $notes
            );
        }

        return sprintf(
            "%s (%s)\n%s",
            $label,
            $dateLabel,
            implode("\n", $lines)
        );
    }

    protected function formatKegiatanMessage(Carbon $date, string $label, $kegiatan, bool $onlyPending): string
    {
        $dateLabel = $this->formatDateIndo($date);

        if ($kegiatan->isEmpty()) {
            $noDataText = $onlyPending
                ? "Tidak ada surat yang belum disposisi pada {$dateLabel}."
                : "Belum ada jadwal pada {$dateLabel}.";

            return $noDataText;
        }

        $lines = [];
        foreach ($kegiatan as $idx => $item) {
            $time      = $item->waktu ?? '-';
            $disposed  = ($item->sudah_disposisi ?? false) ? 'Selesai TL' : 'Belum TL';
            $location  = $item->tempat ? " | Lokasi: {$item->tempat}" : '';
            $notes     = $item->keterangan ? "\n   Catatan: {$item->keterangan}" : '';

            $audienceLine = $this->formatAudience($item, 'Disposisi');
            $attachmentLines = $this->formatAttachments($item);
            $extraLines = '';

            if ($audienceLine) {
                $extraLines .= "\n   {$audienceLine}";
            }

            if ($attachmentLines !== '') {
                $extraLines .= $attachmentLines;
            }

            $lines[] = sprintf(
                "%d. %s - %s%s\n   Status: %s%s%s",
                $idx + 1,
                $time,
                $item->nama_kegiatan,
                $location,
                $disposed,
                $notes,
                $extraLines
            );
        }

        return sprintf(
            "%s (%s)\n%s",
            $label,
            $dateLabel,
            implode("\n", $lines)
        );
    }

    protected function extractText(array $payload): ?string
    {
        $candidates = [
            $payload['message'] ?? null,
            $payload['text'] ?? null,
            data_get($payload, 'message.text') ?? null,
            data_get($payload, 'message.conversation') ?? null,
        ];

        foreach ($candidates as $text) {
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return null;
    }

    protected function extractGroupId(array $payload): ?string
    {
        $group = $payload['group'] ?? null;
        if (is_array($group) && ! empty($group['id'])) {
            return trim((string) $group['id']);
        }

        $candidates = [
            $payload['from'] ?? null,
            $payload['group_id'] ?? null,
        ];

        foreach ($candidates as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Kandidat id_group untuk query (raw, strip suffix, tambah @g.us).
     */
    protected function candidateGroupIds(string $groupId): array
    {
        $clean = trim($groupId);
        $ids = [$clean];

        if (str_contains($clean, '@')) {
            $ids[] = preg_replace('/@.*/', '', $clean);
        } else {
            $ids[] = $clean . '@g.us';
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function normalizeGroupIdValue(string $value): string
    {
        $value = trim($value);
        if (str_ends_with($value, '@g.us')) {
            return preg_replace('/@g\.us$/', '', $value);
        }
        return $value;
    }

    protected function getKegiatanForGroup(array $groupIds, Carbon $date, bool $onlyPending)
    {
        $normalized = collect($groupIds)
            ->map(fn ($id) => $this->normalizeGroupIdValue($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = Kegiatan::query()
            ->whereDate('tanggal', $date->toDateString())
            ->when($onlyPending, function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('sudah_disposisi')
                        ->orWhere('sudah_disposisi', false);
                });
            });

        if (! empty($normalized)) {
            $query->whereHas('groups', function ($q) use ($normalized) {
                $q->whereIn('wa_gateway_group_id', $normalized);
            });
        }

        $results = $query
            ->with(['personils', 'personilCategories'])
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->get();

        // Jika belum ada relasi group_kegiatan, fallback: ambil semua kegiatan di tanggal tersebut.
        if ($results->isEmpty()) {
            $results = Kegiatan::query()
                ->whereDate('tanggal', $date->toDateString())
                ->when($onlyPending, function ($q) {
                    $q->where(function ($w) {
                        $w->whereNull('sudah_disposisi')
                            ->orWhere('sudah_disposisi', false);
                    });
                })
                ->with(['personils', 'personilCategories'])
                ->orderBy('tanggal')
                ->orderBy('waktu')
                ->get();
        }

        return $results;
    }

    protected function getSchedulesForGroup(array $groupIds, Carbon $date, bool $onlyPending)
    {
        return Schedule::query()
            ->whereIn('id_group', $groupIds)
            ->whereDate('starts_at', $date->toDateString())
            ->when($onlyPending, fn ($q) => $q->where('is_disposed', false))
            ->orderBy('starts_at')
            ->get();
    }

    protected function formatDateIndo(Carbon $date): string
    {
        return $date->locale('id')->isoFormat('D MMMM Y');
    }

    protected function formatAudience($kegiatan, string $prefix): ?string
    {
        $categories = $kegiatan->personilCategories ?? collect();
        $categoryLabels = $categories
            ->map(fn (PersonilCategory $cat) => $cat->label_broadcast ?: $cat->nama)
            ->filter()
            ->values()
            ->all();

        $assignedCategorySlugs = $categories->pluck('slug')->filter()->all();

        $persons = $kegiatan->personils ?? collect();
        if (! empty($assignedCategorySlugs)) {
            $persons = $persons->reject(function ($p) use ($assignedCategorySlugs) {
                $kategori = trim((string) ($p->kategori ?? ''));
                return $kategori !== '' && in_array($kategori, $assignedCategorySlugs, true);
            });
        }

        $personLabels = $persons
            ->map(function ($p) {
                $mention = $this->formatMention($p->no_wa ?? null);
                if ($mention) {
                    return $mention;
                }
                return $p->nama ?? null;
            })
            ->filter()
            ->values()
            ->all();

        $parts = array_filter(array_merge($categoryLabels, $personLabels));

        if (empty($parts)) {
            return null;
        }

        return $prefix . ': ' . implode(', ', $parts);
    }

    protected function formatMention(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return '@' . $digits;
    }

    protected function formatAttachments($kegiatan): string
    {
        $lines = '';

        $suratUrl = $this->buildPublicUrl($kegiatan->surat_undangan ?? null);
        $lampiranUrl = $this->buildPublicUrl($kegiatan->lampiran_surat ?? null);

        if ($suratUrl) {
            $lines .= "\n   \ud83d\udcce Surat: {$suratUrl}";
        }

        if ($lampiranUrl) {
            $lines .= "\n   \ud83d\udcce Lampiran: {$lampiranUrl}";
        }

        return $lines;
    }

    protected function buildPublicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $encoded = collect(explode('/', $path))
                ->map(fn ($part) => rawurlencode($part))
                ->implode('/');

            return Storage::disk('public')->url($encoded);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
