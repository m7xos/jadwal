<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\Group;
use App\Models\Personil;
use App\Models\PersonilCategory;
use App\Models\WaGatewaySetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class WaGatewayService
{
    protected string $baseUrl;
    protected string $token;
    protected ?string $secretKey;
    protected string $provider;
    protected string $groupId;
    protected array $groupMappings;
    protected string $masterKey;

    public function __construct()
    {
        $settings = WaGatewaySetting::current();

        $this->baseUrl = rtrim(
            (string) ($settings->base_url ?: config('wa_gateway.base_url', 'http://localhost:5001')),
            '/'
        );
        $this->token = (string) ($settings->token ?: config('wa_gateway.token', ''));
        $this->secretKey = $settings->secret_key ?: config('wa_gateway.secret_key');
        $this->provider = (string) ($settings->provider ?: config('wa_gateway.provider', 'wa-gateway'));
        $this->groupMappings = $settings->groupMappings();
        $this->masterKey = (string) ($settings->key ?: config('wa_gateway.key', ''));
        $this->groupId   = $this->resolveDefaultGroupId();
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== ''
            && $this->token !== ''
            && $this->groupId !== '';
    }

    protected function getAuthHeaderValue(): string
    {
        if (! empty($this->secretKey)) {
            return $this->token . '.' . $this->secretKey;
        }

        return $this->token;
    }

    protected function isWaGateway(): bool
    {
        return $this->provider === 'wa-gateway';
    }

     /**
      * Normalisasi group id supaya kompatibel dengan provider.
      *
     * - Legacy: group id biasanya numerik (tanpa suffix).
     * - wa-gateway: group id perlu format JID (contoh: 1203...@g.us).
     */
    protected function normalizeGroupId(string $groupId): string
    {
        $groupId = trim($groupId);

        if ($groupId === '' || ! $this->isWaGateway()) {
            return $groupId;
        }

        if (str_contains($groupId, '@')) {
            return $groupId;
        }

        return $groupId . '@g.us';
    }

    protected function client()
    {
        $headers = [
            'Authorization' => $this->getAuthHeaderValue(),
            'Content-Type'  => 'application/json',
        ];

        $masterKey = trim($this->masterKey);
        if ($masterKey !== '') {
            $headers['key'] = $masterKey;
        }

        return Http::withHeaders($headers)
            ->withOptions([
                'verify' => false,
            ]);
    }

    protected function getSuratUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $relativeUrl = Storage::disk('public')->url($this->encodePathForUrl($path));

        return URL::to($relativeUrl);
    }

    protected function hasClientCredentials(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    protected function resolveDefaultGroupId(): string
    {
        $default = Group::query()
            ->where('is_default', true)
            ->whereNotNull('wa_gateway_group_id')
            ->where('wa_gateway_group_id', '!=', '')
            ->first();

        if ($default) {
            return trim((string) $default->wa_gateway_group_id);
        }

        $fallback = Group::query()
            ->whereNotNull('wa_gateway_group_id')
            ->where('wa_gateway_group_id', '!=', '')
            ->orderBy('id')
            ->first();

        if ($fallback) {
            return trim((string) $fallback->wa_gateway_group_id);
        }

        return '';
    }

    protected function getShortSuratUrl(?Kegiatan $kegiatan): ?string
    {
        if (! $kegiatan || ! $kegiatan->surat_undangan) {
            return null;
        }

        return URL::route('kegiatan.surat.short', ['kegiatan' => $kegiatan->id]);
    }

    protected function getLampiranUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $relativeUrl = Storage::disk('public')->url($this->encodePathForUrl($path));

        return URL::to($relativeUrl);
    }

    protected function formatMention(?string $number): ?string
    {
        $normalized = $this->normalizePhone($number);

        if (! $normalized) {
            return null;
        }

        return '@' . $normalized;
    }

    /**
     * Format pesan pengingat batas waktu tindak lanjut.
     */
    protected function buildTindakLanjutReminderMessage(Kegiatan $kegiatan): string
    {
        $kegiatan->loadMissing('personils');

        $lines = [];

        $nomorSurat = trim((string) ($kegiatan->nomor ?? ''));
        if ($nomorSurat === '') {
            $nomorSurat = '-';
        }

        $perihal = trim((string) ($kegiatan->nama_kegiatan ?? ''));
        if ($perihal === '') {
            $perihal = '-';
        }

        $kodePengingat = 'TL-' . $kegiatan->id;

        $lines[] = '*Pengingat TL Surat Nomor: ' . $nomorSurat . '*';
        $lines[] = '';
        $lines[] = $this->formatLabelLine('Kode TL', $kodePengingat);
        $lines[] = $this->formatLabelLine('Perihal', $perihal);
        $lines[] = $this->formatLabelLine('Tanggal', $kegiatan->tanggal_label ?? '-');

        $deadline = $kegiatan->batas_tindak_lanjut ?? $kegiatan->tindak_lanjut_deadline;
        $deadlineLabel = '-';

        if ($deadline) {
            $deadlineLabel = $deadline
                ->locale('id')
                ->isoFormat('dddd, D MMMM Y HH:mm') . ' WIB';
        } elseif ($kegiatan->tindak_lanjut_deadline_label) {
            $deadlineLabel = $kegiatan->tindak_lanjut_deadline_label;
        }

        $lines[] = $this->formatLabelLine('Batas TL', $deadlineLabel);
        $lines[] = '';

        $suratUrl = $this->getShortSuratUrl($kegiatan);
        if ($suratUrl) {
            //$lines[] = '';
            $lines[] = '📎 Surat (PDF):';
            $lines[] = $suratUrl;
            $lines[] = '';
        }

        $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);
        if ($lampiranUrl) {
            $lines[] = '📎 Lampiran Surat:';
            $lines[] = $lampiranUrl;
            $lines[] = '';
        }

        $dispositionTags = $this->getDispositionTags();
        $personilTags = $this->getPersonilTagsForKegiatan($kegiatan);

        if (! empty($dispositionTags) || ! empty($personilTags)) {
            $lines[] = 'Mohon arahan percepatan tindak lanjut:';
            if (! empty($dispositionTags)) {
                $lines[] = implode(' ', $dispositionTags);
            }
            if (! empty($personilTags)) {
                $lines[] = 'kepada: ' . implode(' ', $personilTags);
            }
            $lines[] = '';
        }

        $lines[] = '_Balas pesan ini dengan *TL-' . $kegiatan->id . ' selesai* jika sudah menyelesaikan TL_';
        $lines[] = '';
        $lines[] = '_Pesan ini dikirim otomatis saat batas waktu tindak lanjut tercapai._';

        $fallback = implode("\n", $lines);

        $labelLines = [
            $this->formatLabelLine('Kode TL', $kodePengingat),
            $this->formatLabelLine('Perihal', $perihal),
            $this->formatLabelLine('Tanggal', $kegiatan->tanggal_label ?? '-'),
            $this->formatLabelLine('Batas TL', $deadlineLabel),
        ];

        $disposisiLines = [];
        if (! empty($dispositionTags) || ! empty($personilTags)) {
            $disposisiLines[] = 'Mohon arahan percepatan tindak lanjut:';
            if (! empty($dispositionTags)) {
                $disposisiLines[] = implode(' ', $dispositionTags);
            }
            if (! empty($personilTags)) {
                $disposisiLines[] = 'kepada: ' . implode(' ', $personilTags);
            }
        }

        $data = [
            'nomor_surat' => $nomorSurat,
            'kode_tl' => $kodePengingat,
            'label_lines' => implode("\n", $labelLines),
            'surat_block' => $suratUrl
                ? $this->formatTemplateBlock(['📎 Surat (PDF):', $suratUrl])
                : '',
            'lampiran_block' => $lampiranUrl
                ? $this->formatTemplateBlock(['📎 Lampiran Surat:', $lampiranUrl])
                : '',
            'disposisi_block' => $this->formatTemplateBlock($disposisiLines),
            'balasan_line' => $this->formatTemplateLine(
                '_Balas pesan ini dengan *TL-' . $kegiatan->id . ' selesai* jika sudah menyelesaikan TL_'
            ),
            'footer' => '_Pesan ini dikirim otomatis saat batas waktu tindak lanjut tercapai._',
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('tindak_lanjut_reminder', $data, $fallback);
    }

    protected function getDispositionTags(): array
    {
        $roles = [
            'Camat Watumalang',
            'Sekretaris Kecamatan Watumalang',
        ];

        return Personil::query()
            ->whereIn('jabatan', $roles)
            ->get(['no_wa', 'jabatan', 'nama'])
            ->map(fn (Personil $personil) => $this->formatPersonilTag($personil, true))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function getPersonilTagsForKegiatan(Kegiatan $kegiatan): array
    {
        $personils = $kegiatan->personils ?? collect();

        if ($personils->isEmpty()) {
            return [];
        }

        return $personils
            ->map(fn (Personil $personil) => $this->formatPersonilTag($personil, true))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function formatLabelLine(string $label, string $value): string
    {
        return sprintf('%-14s: %s', $label, $value);
    }

    protected function formatTemplateLine(string $line): string
    {
        if ($line === '') {
            return '';
        }

        return $line . "\n";
    }

    /**
     * @param array<int, string> $lines
     */
    protected function formatTemplateBlock(array $lines): string
    {
        $filtered = array_values(array_filter($lines, fn ($line) => $line !== ''));

        if (empty($filtered)) {
            return '';
        }

        return implode("\n", $filtered) . "\n\n";
    }

    /**
     * @param array<int, string> $lines
     */
    protected function formatTemplateInlineBlock(array $lines): string
    {
        $filtered = array_values(array_filter($lines, fn ($line) => $line !== ''));

        if (empty($filtered)) {
            return '';
        }

        return implode("\n", $filtered) . "\n";
    }

    protected function formatPersonilTag(Personil $personil, bool $withJabatan = false): ?string
    {
        $mention = $this->formatMention($personil->no_wa);
        $name = trim((string) $personil->nama);
        $jabatan = trim((string) $personil->jabatan);

        if (! $mention) {
            if ($name === '') {
                return null;
            }

            $tag = '@' . $name;

            if ($withJabatan && $jabatan !== '') {
                $tag .= ' (' . $jabatan . ')';
            }

            return $tag;
        }

        if ($withJabatan && $jabatan !== '') {
            if ($name !== '') {
                return $mention . ' (' . $name . ' - ' . $jabatan . ')';
            }

            return $mention . ' (' . $jabatan . ')';
        }

        if ($name !== '') {
            return $mention . ' (' . $name . ')';
        }

        return $mention;
    }

    protected function formatMentionWithName(Personil $personil): ?string
    {
        $mention = $this->formatMention($personil->no_wa);

        $name = trim((string) $personil->nama);
        $jabatan = trim((string) $personil->jabatan);
        $label = '';

        if ($name !== '') {
            $label = $name;

            if ($jabatan !== '') {
                $label .= ' - ' . $jabatan;
            }
        } elseif ($jabatan !== '') {
            $label = $jabatan;
        }

        // Jika nomor tidak ada / tidak valid, tetap tampilkan nama saja.
        if (! $mention) {
            return $label !== '' ? $label : null;
        }

        // Jika nomor ada, tampilkan mention + label (kalau tersedia).
        if ($label !== '') {
            return $mention . ' (' . $label . ')';
        }

        return $mention;
    }

    public function sendGroupText(string $message): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $result = $this->sendTextToGroup($this->groupId, $message);

        return $result['success'];
    }

    /**
     * @return array{success: bool, response: mixed, error: string|null}
     */
    protected function sendTextToGroup(string $groupId, string $message): array
    {
        $groupId = $this->normalizeGroupId($groupId);

        if (! $this->hasClientCredentials() || $groupId === '') {
            return [
                'success' => false,
                'error' => 'Konfigurasi WA Gateway belum lengkap',
                'response' => null,
            ];
        }

        $payload = [
            'data' => [
                [
                    'phone' => $groupId,
                    'message' => $message,
                    'isGroup' => true,
                ],
            ],
        ];

        try {
            $response = $this->client()->post($this->baseUrl . '/api/v2/send-message', $payload);
        } catch (\Throwable $exception) {
            Log::error('WA Gateway: HTTP error kirim teks grup', [
                'message' => $exception->getMessage(),
                'group_id' => $groupId,
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            Log::error('WA Gateway: HTTP error kirim teks grup', [
                'status' => $response->status(),
                'body' => $response->body(),
                'group_id' => $groupId,
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
                'response' => $response->json(),
            ];
        }

        $json = $response->json();
        $success = (bool) data_get($json, 'status', false);

        Log::info('WA Gateway: response sendGroupText', [
            'response' => $json,
            'group_id' => $groupId,
        ]);

        return [
            'success' => $success,
            'error' => $success ? null : (data_get($json, 'message') ?: 'Pengiriman gagal'),
            'response' => $json,
        ];
    }

    public function sendTextToSpecificGroup(string $groupId, string $message): array
    {
        return $this->sendTextToGroup($groupId, $message);
    }

    /**
     * Kirim pesan teks ke nomor personal (bukan grup).
     *
     * @param  array<int, string|null>  $numbers
     */
    public function sendPersonalText(array $numbers, string $message): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Konfigurasi WA Gateway belum lengkap',
                'response' => null,
            ];
        }

        $data = [];

        foreach ($numbers as $number) {
            $normalized = $this->normalizePhone($number);

            if (! $normalized) {
                continue;
            }

            $data[] = [
                'phone' => $normalized,
                'message' => $message,
                'isGroup' => 'false',
            ];
        }

        if (empty($data)) {
            return [
                'success' => false,
                'error' => 'Tidak ada nomor WA yang valid.',
                'response' => null,
            ];
        }

        try {
            $response = $this->client()
                ->post($this->baseUrl . '/api/v2/send-message', ['data' => $data]);
        } catch (\Throwable $exception) {
            Log::error('WA Gateway: HTTP error kirim personal text', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            Log::error('WA Gateway: HTTP error kirim personal text', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
                'response' => $response->json(),
            ];
        }

        $json = $response->json();
        $success = (bool) data_get($json, 'status', false);

        return [
            'success' => $success,
            'error' => $success ? null : (data_get($json, 'message') ?: 'Pengiriman gagal'),
            'response' => $json,
        ];
    }

    /**
     * Format pesan rekap untuk banyak kegiatan (untuk grup WA).
     *
     * @param iterable<Kegiatan> $kegiatans
     */
    protected function buildGroupMessage(iterable $kegiatans): string
    {
        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);
        $items = $items->sortBy('tanggal');

        $lines = [];

        // JUDUL
        $lines[] = 'REKAP AGENDA KEGIATAN KANTOR';
        $lines[] = '';

        // Header "Agenda Kamis, 27 November 2025"
        if ($items->isNotEmpty()) {
            /** @var \App\Models\Kegiatan|null $first */
            $first = $items->first();

            if ($first && $first->tanggal) {
                try {
                    $label = $first->tanggal
                        ->locale('id')
                        ->isoFormat('dddd, D MMMM Y');

                    $lines[] = 'Agenda ' . $label;
                    $lines[] = '';
                } catch (\Throwable $e) {
                    // Abaikan error format tanggal
                }
            }
        }

        // ISI AGENDA
        $no = 1;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            // Nomor + nama kegiatan (dibold)
            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';
            //$lines[] = '';

            // Waktu & tempat
            $lines[] = '   ⏰ ' . ($kegiatan->waktu ?? '-');
            $lines[] = '   📍 ' . ($kegiatan->tempat ?? '-');
            $lines[] = '';

            // Personil (Penerima Disposisi)
            $personils = $kegiatan->personils ?? collect();

            if ($personils->isNotEmpty()) {
                $lines[] = '   👥 Penerima Disposisi:';

                $i = 1;
                foreach ($personils as $p) {
                    $nama = trim((string) ($p->nama ?? ''));

                    if ($nama === '') {
                        continue;
                    }

                    $rawNo  = trim((string) ($p->no_wa ?? ''));
                    $digits = preg_replace('/[^0-9]/', '', $rawNo) ?? '';

                    if ($digits !== '') {
                        if (substr($digits, 0, 1) === '0') {
                            $digits = '62' . substr($digits, 1);
                        }

                        $tag = ' @' . $digits;
                    } else {
                        $tag = '';
                    }

                    $lines[] = '      ' . $i . '. ' . $nama . $tag;
                    $i++;
                }

                $lines[] = '';
            }

            // KETERANGAN (hanya kalau diisi)
            $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
            if ($keterangan !== '') {
                $lines[] = '   📝 Keterangan:';
                $lines[] = '      ' . $keterangan;
                $lines[] = '';
            }

            // Link surat singkat
            $suratUrl = $this->getShortSuratUrl($kegiatan);
            if ($suratUrl) {
                $lines[] = '   📎 Link Surat: ' . $suratUrl;
                $lines[] = '';
            }

            $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);
            if ($lampiranUrl) {
                $lines[] = '   📎 Lampiran: ' . $lampiranUrl;
                $lines[] = '';
            }

            $no++;
        }

        if ($no === 1) {
            $lines[] = '(Tidak ada agenda pada hari ini.)';
            $lines[] = '';
        }

        $lines[] = 'Tanggal rekap: ' . now()
            ->locale('id')
            ->translatedFormat('d F Y H:i') . ' WIB';
        $lines[] = '';
        $lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

        $fallback = implode("\n", $lines);

        $tanggalLabel = '';
        if ($items->isNotEmpty()) {
            /** @var \App\Models\Kegiatan|null $first */
            $first = $items->first();

            if ($first && $first->tanggal) {
                try {
                    $tanggalLabel = $first->tanggal
                        ->locale('id')
                        ->isoFormat('dddd, D MMMM Y');
                } catch (\Throwable $e) {
                    $tanggalLabel = '';
                }
            }
        }

        if ($tanggalLabel === '') {
            $tanggalLabel = now()->locale('id')->isoFormat('dddd, D MMMM Y');
        }

        $data = [
            'judul' => 'REKAP AGENDA KEGIATAN KANTOR',
            'tanggal_label' => $tanggalLabel,
            'agenda_list' => $this->buildGroupAgendaList($items),
            'generated_at' => now()->locale('id')->translatedFormat('d F Y H:i') . ' WIB',
            'footer' => 'Pesan ini dikirim otomatis dari sistem agenda kantor.',
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('group_rekap', $data, $fallback);
    }

    protected function buildGroupMessageBelumDisposisi(iterable $kegiatans): string
    {
        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);
        $items = $items->sortBy('tanggal');

        $lines = [];

        $lines[] = '*AGENDA MENUNGGU DISPOSISI PIMPINAN*';
        $lines[] = '';
       // $dispositionTags = $this->getDispositionTags();
       // if (! empty($dispositionTags)) {
       //     $lines[] = 'Arahan disposisi: ' . implode(' ', $dispositionTags) . '.';
       //     $lines[] = '';
        //}
        $lines[] = 'Berikut daftar kegiatan yang belum mendapatkan disposisi pimpinan:';
        $lines[] = '';

        $no = 1;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            if ($no > 1) {
                $lines[] = '────────────────────────';
            }

            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';
            $lines[] = ' *Tanggal*     : ' . ($kegiatan->tanggal_label ?? '-');
            $lines[] = ' *Waktu*       : ' . ($kegiatan->waktu ?? '-');
            $lines[] = ' *Tempat*      : ' . ($kegiatan->tempat ?? '-');
            $lines[] = '';
            $lines[] = '';
            $suratUrl = $this->getShortSuratUrl($kegiatan);
            if ($suratUrl) {
                $lines[] = '📎 *Lihat Surat (PDF)*';
                $lines[] = $suratUrl;
            }

            $lines[] = '';
            $no++;
        }

        if ($no === 1) {
            $lines[] = '_Tidak ada agenda yang berstatus menunggu disposisi._';
        } else {
            $lines[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';
        }

        $leadershipTags = $this->getPersonilTagsByJabatan([
            'Camat Watumalang',
            'Sekretaris Kecamatan Watumalang',
        ]);

        if (! empty($leadershipTags)) {
            $lines[] = '';
            $lines[] = '*Mohon petunjuk/arahan disposisi:*';
            $lines[] = implode(' ', $leadershipTags);
        }

        $lines[] = '';
        $lines[] = '_Pesan ini dikirim otomatis dari sistem agenda kantor._';

        $fallback = implode("\n", $lines);

        $leadershipBlock = '';
        if (! empty($leadershipTags)) {
            $leadershipBlock = $this->formatTemplateBlock([
                '*Mohon petunjuk/arahan disposisi:*',
                implode(' ', $leadershipTags),
            ]);
        }

        $data = [
            'agenda_list' => $this->buildBelumDisposisiAgendaList($items),
            'leadership_block' => $leadershipBlock,
            'footer' => '_Pesan ini dikirim otomatis dari sistem agenda kantor._',
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('group_belum_disposisi', $data, $fallback);
    }

    protected function getPersonilTagsByJabatan(array $jabatanList): array
    {
        $personils = Personil::query()
            ->whereIn('jabatan', $jabatanList)
            ->get(['nama', 'no_wa', 'jabatan']);

        $tags = [];

        foreach ($personils as $personil) {
            $rawNo  = trim((string) ($personil->no_wa ?? ''));
            $digits = preg_replace('/[^0-9]/', '', $rawNo) ?? '';

            if ($digits === '') {
                continue;
            }

            if (substr($digits, 0, 1) === '0') {
                $digits = '62' . substr($digits, 1);
            }

            $tags[] = '@' . $digits;
        }

        return $tags;
    }

    /**
     * Format pesan khusus utk 1 kegiatan ke WA personil.
     */
    protected function buildPersonilMessage(Kegiatan $kegiatan): string
    {
        $lines = [];

        $lines[] = '*UNDANGAN / INFORMASI KEGIATAN*';
        $lines[] = '────────────────────────';
        $lines[] = '';

        $lines[] = '*Nama Kegiatan*';
        $lines[] = '*' . ($kegiatan->nama_kegiatan ?? '-') . '*';
        $lines[] = '';

        $lines[] = '*Nomor Surat*';
        $lines[] = '*' . ($kegiatan->nomor ?? '-') . '*';
        $lines[] = '';

        $lines[] = '*Hari / Tanggal*';
        $lines[] = ($kegiatan->tanggal_label ?? '-');
        $lines[] = '';

        $lines[] = '*Waktu*';
        $lines[] = ($kegiatan->waktu ?? '-');
        $lines[] = '';

        $lines[] = '*Tempat*';
        $lines[] = ($kegiatan->tempat ?? '-');
        $lines[] = '';

        if (! empty($kegiatan->keterangan)) {
            $lines[] = '*Keterangan*';
            $lines[] = $kegiatan->keterangan;
            $lines[] = '';
        }

        $suratUrl = $this->getShortSuratUrl($kegiatan);
        if ($suratUrl) {
            $lines[] = '📎 *Lihat Surat (PDF)*';
            $lines[] = $suratUrl;
            $lines[] = '';
        }

        $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);
        if ($lampiranUrl) {
            $lines[] = '📎 *Lampiran*';
            $lines[] = $lampiranUrl;
            $lines[] = '';
        }

        $lines[] = 'Mohon kehadiran Bapak/Ibu sesuai jadwal di atas.';
        $lines[] = '';
        $lines[] = '_Pesan ini dikirim otomatis. Mohon tidak membalas ke nomor ini._';

        $fallback = implode("\n", $lines);

        $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
        $keteranganBlock = $keterangan !== ''
            ? $this->formatTemplateBlock(['*Keterangan*', $keterangan])
            : '';
        $suratBlock = $suratUrl
            ? $this->formatTemplateBlock(['📎 *Lihat Surat (PDF)*', $suratUrl])
            : '';
        $lampiranBlock = $lampiranUrl
            ? $this->formatTemplateBlock(['📎 *Lampiran*', $lampiranUrl])
            : '';

        $data = [
            'nama_kegiatan' => (string) ($kegiatan->nama_kegiatan ?? '-'),
            'nomor_surat' => (string) ($kegiatan->nomor ?? '-'),
            'tanggal' => (string) ($kegiatan->tanggal_label ?? '-'),
            'waktu' => (string) ($kegiatan->waktu ?? '-'),
            'tempat' => (string) ($kegiatan->tempat ?? '-'),
            'keterangan_block' => $keteranganBlock,
            'surat_block' => $suratBlock,
            'lampiran_block' => $lampiranBlock,
            'footer' => '_Pesan ini dikirim otomatis. Mohon tidak membalas ke nomor ini._',
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('agenda_personil', $data, $fallback);
    }

    /**
     * Bangun pesan agenda untuk dikirim ke beberapa grup WhatsApp.
     *
     * @param  array<int, int|string>  $groupIds
     */
    public function buildAgendaMessageForGroups(Kegiatan $kegiatan, array $groupIds): string
    {
        $kegiatan->loadMissing('personils');

        $lines = [];

        $headerDate = $this->formatAgendaHeaderDate($kegiatan);
        $lines[] = '📌 REKAP AGENDA — ' . $headerDate;
        $lines[] = '';

        $title = trim((string) ($kegiatan->nama_kegiatan ?? '-'));
        $time = trim((string) ($kegiatan->waktu ?? '-'));
        $place = trim((string) ($kegiatan->tempat ?? '-'));
        $participants = $this->formatParticipantsShort($kegiatan);
        $notes = trim((string) ($kegiatan->keterangan ?? ''));
        $suratUrl = $this->getShortSuratUrl($kegiatan);
        $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);

        $lines[] = '#1 ' . ($title !== '' ? $title : '-');
        $lines[] = '   ⏰ ' . ($time !== '' ? $time : '-') . ' | 📍 ' . ($place !== '' ? $place : '-');

        if ($participants !== '') {
            $lines[] = '   👥 ' . $participants;
        }

        if ($notes !== '') {
            $lines[] = '   📝 ' . $notes;
        }

        if ($suratUrl) {
            $lines[] = '   📎 Surat: ' . $suratUrl;
        }

        if ($lampiranUrl) {
            $lines[] = '   📎 Lampiran: ' . $lampiranUrl;
        }

        $lines[] = '';
        $lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

        $fallback = implode("\n", $lines);

        $data = [
            'tanggal_header' => $headerDate,
            'judul' => $title !== '' ? $title : '-',
            'waktu' => $time !== '' ? $time : '-',
            'tempat' => $place !== '' ? $place : '-',
            'peserta_line' => $this->formatTemplateLine($participants !== '' ? '   👥 ' . $participants : ''),
            'keterangan_line' => $this->formatTemplateLine($notes !== '' ? '   📝 ' . $notes : ''),
            'surat_line' => $this->formatTemplateLine($suratUrl ? '   📎 Surat: ' . $suratUrl : ''),
            'lampiran_line' => $this->formatTemplateLine($lampiranUrl ? '   📎 Lampiran: ' . $lampiranUrl : ''),
            'footer' => 'Pesan ini dikirim otomatis dari sistem agenda kantor.',
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('agenda_group', $data, $fallback);
    }

    /**
     * Ambil status device dari wa-gateway (realtime).
     *
     * @return array{success: bool, status?: string, device?: string, phone?: string|null, webhook_url?: string|null, error?: string|null}
     */
    public function getDeviceStatus(): array
    {
        if (! $this->hasClientCredentials()) {
            return [
                'success' => false,
                'error' => 'Konfigurasi WA Gateway belum lengkap',
            ];
        }

        try {
            $response = $this->client()->get($this->baseUrl . '/api/device/info');
        } catch (\Throwable $e) {
            Log::error('WA Gateway: HTTP error get device status', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        if (! $response->successful()) {
            Log::error('WA Gateway: HTTP error get device status', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
            ];
        }

        $json = $response->json();
        $first = is_array($json) ? data_get($json, 'data.0') : null;

        return [
            'success' => true,
            'status' => strtolower((string) ($first['status'] ?? 'unknown')),
            'device' => (string) ($first['device'] ?? ''),
            'phone' => $first['phone'] ?? null,
            'webhook_url' => $first['webhook_url'] ?? null,
        ];
    }

    /**
     * Header tanggal untuk rekap agenda.
     */
    protected function formatAgendaHeaderDate(Kegiatan $kegiatan): string
    {
        try {
            if ($kegiatan->tanggal) {
                return $kegiatan->tanggal
                    ->locale('id')
                    ->isoFormat('dddd, D MMMM Y');
            }
        } catch (\Throwable $e) {
            // Abaikan dan fallback ke tanggal hari ini.
        }

        return now()->locale('id')->isoFormat('dddd, D MMMM Y');
    }

    /**
     * Format peserta/mention singkat untuk satu agenda (gabungan kategori/jabatan dan mention).
     */
    protected function formatParticipantsShort(Kegiatan $kegiatan): string
    {
        $personils = $kegiatan->personils ?? collect();
        if ($personils->isEmpty()) {
            return '';
        }

        $categoryLabels = $personils
            ->pluck('kategori')
            ->filter()
            ->unique()
            ->map(function ($kategori) {
                return PersonilCategory::labelFor($kategori) ?? (string) $kategori;
            })
            ->filter()
            ->values()
            ->all();

        $tags = [];

        foreach ($personils as $personil) {
            $jabatan = trim((string) ($personil->jabatan ?? ''));
            $mention = $this->formatMention($personil->no_wa);
            $nama = trim((string) ($personil->nama ?? ''));

            if ($jabatan !== '') {
                $tags[] = $jabatan;
            }

            if ($mention) {
                $tags[] = $mention;
            } elseif ($nama !== '') {
                $tags[] = $nama;
            }
        }

        $parts = array_unique(array_filter(array_merge($categoryLabels, $tags)));

        return implode(', ', $parts);
    }

    protected function buildGroupAgendaList(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '(Tidak ada agenda pada hari ini.)';
        }

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);
        $meta = $templateService->metaFor('group_rekap');
        $itemTemplate = trim((string) ($meta['item_template'] ?? ''));
        $separator = (string) ($meta['item_separator'] ?? "\n\n");

        if ($itemTemplate !== '') {
            $rendered = [];
            $no = 1;

            /** @var \App\Models\Kegiatan $kegiatan */
            foreach ($items as $kegiatan) {
                $personilLines = [];
                $personils = $kegiatan->personils ?? collect();

                if ($personils->isNotEmpty()) {
                    $personilLines[] = '   👥 Penerima Disposisi:';

                    $i = 1;
                    foreach ($personils as $p) {
                        $nama = trim((string) ($p->nama ?? ''));

                        if ($nama === '') {
                            continue;
                        }

                        $rawNo  = trim((string) ($p->no_wa ?? ''));
                        $digits = preg_replace('/[^0-9]/', '', $rawNo) ?? '';

                        if ($digits !== '') {
                            if (substr($digits, 0, 1) === '0') {
                                $digits = '62' . substr($digits, 1);
                            }

                            $tag = ' @' . $digits;
                        } else {
                            $tag = '';
                        }

                        $personilLines[] = '      ' . $i . '. ' . $nama . $tag;
                        $i++;
                    }
                }

                $keteranganLines = [];
                $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
                if ($keterangan !== '') {
                    $keteranganLines[] = '   📝 Keterangan:';
                    $keteranganLines[] = '      ' . $keterangan;
                }

                $suratUrl = $this->getShortSuratUrl($kegiatan);
                $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);

                $data = [
                    'no' => (string) $no,
                    'judul' => (string) ($kegiatan->nama_kegiatan ?? '-'),
                    'waktu' => (string) ($kegiatan->waktu ?? '-'),
                    'tempat' => (string) ($kegiatan->tempat ?? '-'),
                    'personil_block' => $this->formatTemplateInlineBlock($personilLines),
                    'keterangan_block' => $this->formatTemplateInlineBlock($keteranganLines),
                    'surat_line' => $this->formatTemplateLine(
                        $suratUrl ? '   📎 Link Surat: ' . $suratUrl : ''
                    ),
                    'lampiran_line' => $this->formatTemplateLine(
                        $lampiranUrl ? '   📎 Lampiran: ' . $lampiranUrl : ''
                    ),
                ];

                $rendered[] = $templateService->renderString($itemTemplate, $data);
                $no++;
            }

            return implode($separator, $rendered);
        }

        $lines = [];
        $no = 1;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';
            $lines[] = '   ⏰ ' . ($kegiatan->waktu ?? '-');
            $lines[] = '   📍 ' . ($kegiatan->tempat ?? '-');
            $lines[] = '';

            $personils = $kegiatan->personils ?? collect();

            if ($personils->isNotEmpty()) {
                $lines[] = '   👥 Penerima Disposisi:';

                $i = 1;
                foreach ($personils as $p) {
                    $nama = trim((string) ($p->nama ?? ''));

                    if ($nama === '') {
                        continue;
                    }

                    $rawNo  = trim((string) ($p->no_wa ?? ''));
                    $digits = preg_replace('/[^0-9]/', '', $rawNo) ?? '';

                    if ($digits !== '') {
                        if (substr($digits, 0, 1) === '0') {
                            $digits = '62' . substr($digits, 1);
                        }

                        $tag = ' @' . $digits;
                    } else {
                        $tag = '';
                    }

                    $lines[] = '      ' . $i . '. ' . $nama . $tag;
                    $i++;
                }

                $lines[] = '';
            }

            $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
            if ($keterangan !== '') {
                $lines[] = '   📝 Keterangan:';
                $lines[] = '      ' . $keterangan;
                $lines[] = '';
            }

            $suratUrl = $this->getShortSuratUrl($kegiatan);
            if ($suratUrl) {
                $lines[] = '   📎 Link Surat: ' . $suratUrl;
                $lines[] = '';
            }

            $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);
            if ($lampiranUrl) {
                $lines[] = '   📎 Lampiran: ' . $lampiranUrl;
                $lines[] = '';
            }

            $no++;
        }

        return implode("\n", $lines);
    }

    protected function buildBelumDisposisiAgendaList(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '_Tidak ada agenda yang berstatus menunggu disposisi._';
        }

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);
        $meta = $templateService->metaFor('group_belum_disposisi');
        $itemTemplate = trim((string) ($meta['item_template'] ?? ''));
        $separator = (string) ($meta['item_separator'] ?? "\n\n");

        if ($itemTemplate !== '') {
            $rendered = [];
            $no = 1;

            /** @var \App\Models\Kegiatan $kegiatan */
            foreach ($items as $kegiatan) {
                $suratUrl = $this->getShortSuratUrl($kegiatan);
                $suratBlock = '';

                if ($suratUrl) {
                    $suratBlock = $this->formatTemplateInlineBlock([
                        '📎 *Lihat Surat (PDF)*',
                        $suratUrl,
                    ]);
                }

                $data = [
                    'no' => (string) $no,
                    'judul' => (string) ($kegiatan->nama_kegiatan ?? '-'),
                    'tanggal' => (string) ($kegiatan->tanggal_label ?? '-'),
                    'waktu' => (string) ($kegiatan->waktu ?? '-'),
                    'tempat' => (string) ($kegiatan->tempat ?? '-'),
                    'surat_block' => $suratBlock,
                ];

                $rendered[] = $templateService->renderString($itemTemplate, $data);
                $no++;
            }

            $rendered[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';

            return implode($separator, $rendered);
        }

        $lines = [];
        $no = 1;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            if ($no > 1) {
                $lines[] = '────────────────────────';
            }

            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';
            $lines[] = ' *Tanggal*     : ' . ($kegiatan->tanggal_label ?? '-');
            $lines[] = ' *Waktu*       : ' . ($kegiatan->waktu ?? '-');
            $lines[] = ' *Tempat*      : ' . ($kegiatan->tempat ?? '-');
            $lines[] = '';
            $lines[] = '';

            $suratUrl = $this->getShortSuratUrl($kegiatan);
            if ($suratUrl) {
                $lines[] = '📎 *Lihat Surat (PDF)*';
                $lines[] = $suratUrl;
            }

            $lines[] = '';
            $no++;
        }

        $lines[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, int|string>  $groupIds
     * @return array{success: bool, results: array<int, array{success: bool, response: mixed, error: string|null}>}
     */
    public function sendAgendaToGroups(Kegiatan $kegiatan, array $groupIds): array
    {
        if (! $this->hasClientCredentials()) {
            return [
                'success' => false,
                'results' => [],
            ];
        }

        $rawIds = collect($groupIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values();

        if ($rawIds->isEmpty()) {
            $groups = $this->resolveDefaultGroups();
        } else {
            $numericIds = $rawIds
                ->filter(fn ($id) => ctype_digit($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $groups = Group::query()
                ->where(function ($query) use ($numericIds, $rawIds) {
                    $hasCondition = false;

                    if ($numericIds->isNotEmpty()) {
                        $query->whereIn('id', $numericIds);
                        $hasCondition = true;
                    }

                    if ($rawIds->isNotEmpty()) {
                        $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('wa_gateway_group_id', $rawIds);
                    }
                })
                ->get();
        }

        if ($groups->isEmpty()) {
            Log::warning('WA Gateway: sendAgendaToGroups tidak menemukan grup tujuan', [
                'input_ids' => $rawIds->all(),
            ]);

            return [
                'success' => false,
                'results' => [],
            ];
        }

        $results = [];
        $success = false;

        foreach ($groups as $group) {
            $message = $this->buildAgendaMessageForGroups($kegiatan, [$group->id]);

            $phone = $this->resolveGroupPhone($group);

            if (! $phone) {
                Log::warning('WA Gateway: ID grup WA tidak tersedia', [
                    'group_id' => $group->id,
                    'group_name' => $group->nama,
                ]);

                $results[$group->id] = [
                    'success' => false,
                    'error' => 'ID grup WA tidak tersedia',
                    'response' => null,
                ];

                continue;
            }

            $sendResult = $this->sendTextToGroup($phone, $message);
            $results[$group->id] = $sendResult;

            if ($sendResult['success']) {
                $success = true;
            }
        }

        return [
            'success' => $success,
            'results' => $results,
        ];
    }

    protected function resolveDefaultGroups(): Collection
    {
        $defaults = Group::query()
            ->where('is_default', true)
            ->whereNotNull('wa_gateway_group_id')
            ->where('wa_gateway_group_id', '!=', '')
            ->get();

        if ($defaults->isNotEmpty()) {
            return $defaults;
        }

        return Group::query()
            ->whereNotNull('wa_gateway_group_id')
            ->where('wa_gateway_group_id', '!=', '')
            ->orderBy('id')
            ->limit(1)
            ->get();
    }

    protected function encodePathForUrl(string $path): string
    {
        $segments = array_map('rawurlencode', explode('/', $path));

        return implode('/', $segments);
    }

    protected function resolveGroupPhone(Group $group): ?string
    {
        $stored = trim((string) $group->wa_gateway_group_id);

        if ($stored !== '') {
            return $stored;
        }

        $key = Str::slug((string) $group->nama, '_');
        $candidates = [];

        if ($key !== '') {
            $candidates[] = $key;
            $altKeys = [
                str_replace('grup_', 'group_', $key),
                str_replace('group_', 'grup_', $key),
            ];

            foreach ($altKeys as $altKey) {
                if ($altKey !== $key) {
                    $candidates[] = $altKey;
                }
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            $mapped = $this->groupMappings[$candidate] ?? null;

            if (is_string($mapped)) {
                $mapped = trim($mapped);

                if ($mapped !== '') {
                    return $mapped;
                }
            }
        }

        return null;
    }

    /**
     * Ambil grup WA yang relevan untuk TL (grup kegiatan, jika kosong pakai grup personil).
     *
     * @return array<int,string> daftar ID/phone grup unik yang siap dikirimi pesan
     */
    public function getTlTargetGroupPhones(Kegiatan $kegiatan): array
    {
        $kegiatan->loadMissing('groups', 'personils.groups');

        $groups = collect();

        if ($kegiatan->groups && $kegiatan->groups->isNotEmpty()) {
            $groups = $kegiatan->groups;
        } else {
            $groups = $kegiatan->personils
                ? $kegiatan->personils
                    ->flatMap(fn (Personil $p) => $p->groups)
                    ->filter()
                : collect();
        }

        return $groups
            ->filter(fn ($g) => filled($g?->wa_gateway_group_id))
            ->map(fn ($g) => $this->resolveGroupPhone($g) ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function sendGroupTindakLanjutReminder(Kegiatan $kegiatan): array
    {
        if (! $this->isConfigured()) {
            Log::error('WA Gateway: konfigurasi belum lengkap untuk pengingat TL', [
                'base_url'  => $this->baseUrl,
                'token_set' => $this->token !== '',
                'group_id'  => $this->groupId,
            ]);

            return [
                'success' => false,
                'error' => 'Konfigurasi WA Gateway tidak lengkap',
                'response' => null,
            ];
        }

        $phones = $this->getTlTargetGroupPhones($kegiatan);

        if (empty($phones)) {
            Log::warning('WA Gateway: tidak ada grup WA target untuk pengingat TL', [
                'kegiatan_id' => $kegiatan->id,
            ]);

            return [
                'success' => false,
                'error' => 'Tidak ada grup WA personil untuk kegiatan ini',
                'response' => null,
            ];
        }

        $message = $this->buildTindakLanjutReminderMessage($kegiatan);

        $results = [];
        $success = false;

        foreach ($phones as $phone) {
            $sendResult = $this->sendTextToGroup($phone, $message);
            $results[$phone] = $sendResult;

            if ($sendResult['success']) {
                $success = true;
            }
        }

        return [
            'success' => $success,
            'response' => $results,
            'error' => $success ? null : 'Gagal mengirim ke semua grup',
        ];
    }

    /**
     * @return array{success: bool, error: string|null, response: mixed}
     */
    public function sendGroupRekap(iterable $kegiatans): array
    {
        if (! $this->isConfigured()) {
            Log::error('WA Gateway: konfigurasi belum lengkap', [
                'base_url'  => $this->baseUrl,
                'token_set' => $this->token !== '',
                'group_id'  => $this->groupId,
            ]);

            return [
                'success' => false,
                'error' => 'Konfigurasi WA belum lengkap (base_url/token/default group).',
                'response' => null,
            ];
        }

        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if ($items->isEmpty()) {
            Log::warning('WA Gateway: sendGroupRekap dipanggil tanpa data kegiatan');

            return [
                'success' => false,
                'error' => 'Tidak ada data agenda untuk direkap.',
                'response' => null,
            ];
        }

        $message = $this->buildGroupMessage($items);

        $targetGroupId = $this->normalizeGroupId($this->groupId);

        $payload = [
            'data' => [
                [
                    'phone'   => $targetGroupId,
                    'message' => $message,
                    'isGroup' => 'true',
                ],
            ],
        ];

        try {
            $response = $this->client()
                ->post($this->baseUrl . '/api/v2/send-message', $payload);
        } catch (\Throwable $exception) {
            Log::error('WA Gateway: HTTP error kirim group', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            Log::error('WA Gateway: HTTP error kirim group', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $detail = data_get($response->json(), 'message') ?: $response->body();

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status() . ($detail ? (': ' . $detail) : ''),
                'response' => $response->json(),
            ];
        }

        $json = $response->json();

        Log::info('WA Gateway: response sendGroupRekap', [
            'response' => $json,
        ]);

        $success = (bool) data_get($json, 'status', false);

        return [
            'success' => $success,
            'error' => $success ? null : (data_get($json, 'message') ?: 'Pengiriman gagal'),
            'response' => $json,
        ];
    }

    public function sendGroupBelumDisposisi(iterable $kegiatans): bool
    {
        if (! $this->isConfigured()) {
            Log::error('WA Gateway: konfigurasi belum lengkap untuk sendGroupBelumDisposisi', [
                'base_url'  => $this->baseUrl,
                'token_set' => $this->token !== '',
                'group_id'  => $this->groupId,
            ]);

            return false;
        }

        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if ($items->isEmpty()) {
            Log::info('WA Gateway: sendGroupBelumDisposisi dipanggil tanpa data kegiatan');

            return false;
        }

        $message = $this->buildGroupMessageBelumDisposisi($items);

        $payload = [
            'data' => [
                [
                    'phone'   => $this->normalizeGroupId($this->groupId),
                    'message' => $message,
                    'isGroup' => 'true',
                ],
            ],
        ];

        $response = $this->client()
            ->post($this->baseUrl . '/api/v2/send-message', $payload);

        if (! $response->successful()) {
            Log::error('WA Gateway: HTTP error kirim agenda belum disposisi', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();

        Log::info('WA Gateway: response sendGroupBelumDisposisi', [
            'response' => $json,
        ]);

        return (bool) data_get($json, 'status', false);
    }

    protected function normalizePhone(?string $number): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) ($number ?? '')) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    public function sendToPersonils(Kegiatan $kegiatan): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $kegiatan->loadMissing('personils');

        $personils = $kegiatan->personils ?? collect();

        if ($personils->isEmpty()) {
            return false;
        }

        $message = $this->buildPersonilMessage($kegiatan);

        $data = [];

        foreach ($personils as $p) {
            $noWa = trim($p->no_wa);

            if ($noWa === '') {
                continue;
            }

            $data[] = [
                'phone'   => $noWa,
                'message' => $message,
                'isGroup' => 'false',
            ];
        }

        if (empty($data)) {
            return false;
        }

        $payload = ['data' => $data];

        $response = $this->client()
            ->post($this->baseUrl . '/api/v2/send-message', $payload);

        if (! $response->successful()) {
            Log::error('WA Gateway: HTTP error kirim ke personil', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();

        Log::info('WA Gateway: response sendToPersonils', [
            'response' => $json,
        ]);

        return (bool) data_get($json, 'status', false);
    }
}
