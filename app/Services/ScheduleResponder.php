<?php

namespace App\Services;

use App\Models\Group;
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

        [$start, $end, $onlyPending, $label, $isRange] = $this->commandMeta($command);

        $groupCandidates = $this->candidateGroupIds($groupIdRaw);
        $agendaScope = $this->resolveAgendaScope($groupCandidates);
        $onlyPkk = $agendaScope === 'pkk_only';
        $skipFallback = $agendaScope === 'linked_only' || $onlyPkk;

        // Cari data dari tabel kegiatan (utama) dan fallback ke tabel schedules jika ada.
        if ($onlyPkk) {
            $kegiatan = $this->getKegiatanForGroup($groupCandidates, $start, $end, $onlyPending, true, $skipFallback);
            $schedules = collect();
        } else {
            $kegiatan = $this->getKegiatanForGroup($groupCandidates, $start, $end, $onlyPending, false, $skipFallback);
            $schedules = $kegiatan->isNotEmpty()
                ? collect()
                : $this->getSchedulesForGroup($groupCandidates, $start, $end, $onlyPending);
        }

        if ($kegiatan->isNotEmpty()) {
            $message = $onlyPending
                ? $waGateway->formatGroupBelumDisposisiMessage($kegiatan)
                : $waGateway->formatGroupRekapMessage($kegiatan);

            if ($isRange) {
                $message = $this->prependRangeHeader($label, $start, $end, $message);
            }
        } else {
            $message = $isRange
                ? $this->formatScheduleRangeMessage($start, $end, $label, $schedules, $onlyPending)
                : $this->formatScheduleMessage($start, $label, $schedules, $onlyPending);
        }

        $sent = $waGateway->sendTextToSpecificGroup($groupIdRaw, $message);
        $sentOk = (bool) ($sent['success'] ?? false);

        Log::info('ScheduleResponder processed command', [
            'group'        => $groupIdRaw,
            'command'      => $command,
            'date'         => $start->toDateString(),
            'end_date'     => $end->toDateString(),
            'only_pending' => $onlyPending,
            'is_range'     => $isRange,
            'count_kegiatan' => $kegiatan->count(),
            'count_schedules' => $schedules->count(),
            'sent'         => $sentOk,
            'agenda_scope' => $agendaScope,
            'error'        => $sentOk ? null : ($sent['error'] ?? null),
        ]);

        return true;
    }

    protected function detectCommand(string $text): ?string
    {
        $text = mb_strtolower($text);

        $hasJadwal   = str_contains($text, 'jadwal');
        $hasKegiatan = str_contains($text, 'kegiatan');
        $hasAgenda   = str_contains($text, 'agenda');
        $hasHariIni  = str_contains($text, 'hari ini');
        $hasBesok    = str_contains($text, 'besok');
        $hasPending  = str_contains($text, 'belum disposisi');
        $hasPlus7 = preg_match('/\+\s*7\b/', $text) === 1;
        $hasMinus7 = preg_match('/-\s*7\b/', $text) === 1;
        $hasCommand  = $hasJadwal || $hasKegiatan || $hasAgenda;

        if ($hasCommand && $hasPending && $hasPlus7) {
            return 'future7_pending';
        }

        if ($hasCommand && $hasPending && $hasMinus7) {
            return 'past7_pending';
        }

        if ($hasCommand && $hasPending && $hasHariIni) {
            return 'today_pending';
        }

        if ($hasCommand && $hasPending && $hasBesok) {
            return 'tomorrow_pending';
        }

        // Jika minta "belum disposisi" tanpa sebut hari, default hari ini.
        if ($hasCommand && $hasPending) {
            return 'today_pending';
        }

        if ($hasCommand && $hasHariIni) {
            return 'today_all';
        }

        if ($hasCommand && $hasBesok) {
            return 'tomorrow_all';
        }

        return null;
    }

    protected function commandMeta(string $command): array
    {
        return match ($command) {
            'today_pending'    => [Carbon::today(), Carbon::today(), true, 'Agenda belum disposisi hari ini', false],
            'tomorrow_pending' => [Carbon::today()->addDay(), Carbon::today()->addDay(), true, 'Agenda belum disposisi besok', false],
            'today_all'        => [Carbon::today(), Carbon::today(), false, 'Agenda kegiatan hari ini', false],
            'tomorrow_all'     => [Carbon::today()->addDay(), Carbon::today()->addDay(), false, 'Agenda kegiatan besok', false],
            'future7_pending'  => [Carbon::today(), Carbon::today()->addDays(7), true, 'Agenda belum disposisi 7 hari ke depan', true],
            'past7_pending'    => [Carbon::today()->subDays(7), Carbon::today()->subDay(), true, 'Agenda belum disposisi 7 hari ke belakang', true],
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

        if ($onlyPending) {
            $lines[] = '*AGENDA MENUNGGU DISPOSISI PIMPINAN*';
            $lines[] = '';
            $lines[] = 'Berikut daftar kegiatan yang belum mendapatkan disposisi pimpinan:';
            $lines[] = '';

            foreach ($schedules as $idx => $schedule) {
                if ($idx > 0) {
                    $lines[] = '------------------------';
                }

                $time = $schedule->starts_at ? $schedule->starts_at->format('H:i') : '-';
                $place = $schedule->location ?: '-';
                $notes = trim((string) ($schedule->notes ?? ''));

                $lines[] = '*' . ($idx + 1) . '. ' . ($schedule->title ?? '-') . '*';
                $lines[] = ' *Tanggal*     : ' . $dateLabel;
                $lines[] = ' *Waktu*       : ' . $time;
                $lines[] = ' *Tempat*      : ' . $place;
                $lines[] = '';
                $lines[] = '';

                if ($notes !== '') {
                    $lines[] = '   Keterangan: ' . $notes;
                    $lines[] = '';
                }
            }

            $lines[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';
            $lines[] = '';
            $lines[] = '_Harap selalu laporkan hasil kegiatan kepada atasan._';
            $lines[] = '_Pesan ini dikirim otomatis dari sistem agenda kantor._';

            return implode("\n", $lines);
        }

        $lines[] = 'REKAP AGENDA KEGIATAN KANTOR';
        $lines[] = '';
        $lines[] = 'Agenda ' . $dateLabel;
        $lines[] = '';

        foreach ($schedules as $idx => $schedule) {
            $time = $schedule->starts_at ? $schedule->starts_at->format('H:i') : '-';
            $place = $schedule->location ?: '-';
            $notes = trim((string) ($schedule->notes ?? ''));

            $lines[] = '*' . ($idx + 1) . '. ' . ($schedule->title ?? '-') . '*';
            $lines[] = '   Waktu: ' . $time;
            $lines[] = '   Tempat: ' . $place;

            if ($notes !== '') {
                $lines[] = '   Keterangan: ' . $notes;
            }

            $lines[] = '';
        }

        $lines[] = 'Tanggal rekap: ' . now()
            ->locale('id')
            ->translatedFormat('d F Y H:i') . ' WIB';
        $lines[] = '';
        $lines[] = 'Harap selalu laporkan hasil kegiatan kepada atasan.';
        $lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

        return implode("\n", $lines);
    }

    protected function formatScheduleRangeMessage(
        Carbon $start,
        Carbon $end,
        string $label,
        $schedules,
        bool $onlyPending
    ): string {
        $rangeLabel = $this->formatDateRangeIndo($start, $end);

        if ($schedules->isEmpty()) {
            $noDataText = $onlyPending
                ? "Tidak ada surat yang belum disposisi pada periode {$rangeLabel}."
                : "Belum ada jadwal pada periode {$rangeLabel}.";

            return $noDataText;
        }

        $lines = [
            '*' . $label . '*',
            'Periode: ' . $rangeLabel,
            '',
        ];

        if ($onlyPending) {
            $lines[] = 'Berikut daftar kegiatan yang belum mendapatkan disposisi pimpinan:';
            $lines[] = '';
        } else {
            $lines[] = 'REKAP AGENDA KEGIATAN KANTOR';
            $lines[] = '';
        }

        foreach ($schedules as $idx => $schedule) {
            if ($idx > 0) {
                $lines[] = '------------------------';
            }

            $time = $schedule->starts_at ? $schedule->starts_at->format('H:i') : '-';
            $place = $schedule->location ?: '-';
            $notes = trim((string) ($schedule->notes ?? ''));
            $dateLabel = $this->formatScheduleDateLabel($schedule->starts_at ?? null);

            $lines[] = '*' . ($idx + 1) . '. ' . ($schedule->title ?? '-') . '*';
            $lines[] = ' *Tanggal*     : ' . $dateLabel;
            $lines[] = ' *Waktu*       : ' . $time;
            $lines[] = ' *Tempat*      : ' . $place;
            $lines[] = '';
            $lines[] = '';

            if ($notes !== '') {
                $lines[] = '   Keterangan: ' . $notes;
                $lines[] = '';
            }
        }

        if ($onlyPending) {
            $lines[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';
            $lines[] = '';
            $lines[] = '_Harap selalu laporkan hasil kegiatan kepada atasan._';
            $lines[] = '_Pesan ini dikirim otomatis dari sistem agenda kantor._';

            return implode("\n", $lines);
        }

        $lines[] = 'Tanggal rekap: ' . now()
            ->locale('id')
            ->translatedFormat('d F Y H:i') . ' WIB';
        $lines[] = '';
        $lines[] = 'Harap selalu laporkan hasil kegiatan kepada atasan.';
        $lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

        return implode("\n", $lines);
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

    protected function resolveAgendaScope(array $groupIds): string
    {
        $normalizedIncoming = collect($groupIds)
            ->flatMap(fn ($id) => [$id, $this->normalizeGroupIdValue((string) $id)])
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique();

        if ($normalizedIncoming->isEmpty()) {
            return 'default';
        }

        $groups = Group::query()
            ->whereNotNull('wa_gateway_group_id')
            ->where('wa_gateway_group_id', '!=', '')
            ->get(['wa_gateway_group_id', 'agenda_scope']);

        /** @var ?Group $matched */
        $matched = $groups->first(function (Group $group) use ($normalizedIncoming) {
            $stored = trim((string) $group->wa_gateway_group_id);
            $storedNormalized = $this->normalizeGroupIdValue($stored);

            return $normalizedIncoming->contains($stored)
                || $normalizedIncoming->contains($storedNormalized);
        });

        if (! $matched) {
            return 'default';
        }

        $scope = $matched->agenda_scope ?? 'default';
        $allowed = ['default', 'linked_only', 'pkk_only'];

        return in_array($scope, $allowed, true) ? $scope : 'default';
    }

    protected function normalizeGroupIdValue(string $value): string
    {
        $value = trim($value);
        if (str_ends_with($value, '@g.us')) {
            return preg_replace('/@g\.us$/', '', $value);
        }
        return $value;
    }

    protected function getKegiatanForGroup(
        array $groupIds,
        Carbon $start,
        Carbon $end,
        bool $onlyPending,
        bool $onlyPkk = false,
        bool $skipFallback = false
    )
    {
        $normalized = collect($groupIds)
            ->map(fn ($id) => $this->normalizeGroupIdValue($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = Kegiatan::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($onlyPending, function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('sudah_disposisi')
                        ->orWhere('sudah_disposisi', false);
                });
            })
            ->when($onlyPkk, fn ($q) => $q->where('is_pkk', true));

        if (! $onlyPkk && ! empty($normalized)) {
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
        if ($results->isEmpty() && ! $onlyPkk && ! $skipFallback) {
            $results = Kegiatan::query()
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
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

    protected function getSchedulesForGroup(array $groupIds, Carbon $start, Carbon $end, bool $onlyPending)
    {
        return Schedule::query()
            ->whereIn('id_group', $groupIds)
            ->whereDate('starts_at', '>=', $start->toDateString())
            ->whereDate('starts_at', '<=', $end->toDateString())
            ->when($onlyPending, fn ($q) => $q->where('is_disposed', false))
            ->orderBy('starts_at')
            ->get();
    }

    protected function formatDateIndo(Carbon $date): string
    {
        return $date->locale('id')->isoFormat('D MMMM Y');
    }

    protected function formatDateRangeIndo(Carbon $start, Carbon $end): string
    {
        return $this->formatDateIndo($start) . ' - ' . $this->formatDateIndo($end);
    }

    protected function formatScheduleDateLabel($value): string
    {
        if (! $value) {
            return '-';
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $this->formatDateIndo($date);
    }

    protected function prependRangeHeader(string $label, Carbon $start, Carbon $end, string $message): string
    {
        $lines = [
            '*' . $label . '*',
            'Periode: ' . $this->formatDateRangeIndo($start, $end),
            '',
            trim($message),
        ];

        return implode("\n", $lines);
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
