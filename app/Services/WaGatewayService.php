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
    protected const WA_WRAP_WIDTH = 48;

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
            && $this->groupId !== ''
            && trim($this->masterKey) !== '';
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

    public function getContactName(string $phone): ?string
    {
        if (! $this->hasClientCredentials()) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        try {
            $response = $this->client()
                ->get($this->baseUrl . '/api/v2/contact', ['phone' => $digits]);
        } catch (\Throwable $e) {
            Log::error('WA Gateway: HTTP error get contact', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WA Gateway: HTTP error get contact', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $name = trim((string) data_get($response->json(), 'data.0.name', ''));

        return $name !== '' ? $name : null;
    }

    public function resolveLidNumber(string $lid): ?string
    {
        if (! $this->hasClientCredentials()) {
            return null;
        }

        $lid = trim($lid);
        if ($lid === '' || ! str_contains($lid, '@lid')) {
            return null;
        }

        try {
            $response = $this->client()
                ->get($this->baseUrl . '/api/v2/resolve-lid', ['lid' => $lid]);
        } catch (\Throwable $e) {
            Log::error('WA Gateway: HTTP error resolve lid', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('WA Gateway: HTTP error resolve lid', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $phone = (string) data_get($response->json(), 'data.phone', '');
        if ($phone === '') {
            $jid = (string) data_get($response->json(), 'data.jid', '');
            if ($jid !== '') {
                $phone = preg_replace('/@.*/', '', $jid) ?? '';
            }
        }

        $normalized = $this->normalizePhone($phone);

        return $normalized !== '' ? $normalized : null;
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

    protected function includePersonilTagForTemplate(string $key): bool
    {
        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->includePersonilTag($key, true);
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
        $tanggalLabel = (string) ($kegiatan->tanggal_label ?? '-');
        $lines[] = $this->formatLabelLine('Tanggal', $tanggalLabel);

        $deadlineLabel = $this->formatTindakLanjutDeadlineLabel($kegiatan);

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

        $includeTag = $this->includePersonilTagForTemplate('tindak_lanjut_reminder');
        $dispositionTags = $this->getDispositionTags($includeTag);
        $personilTags = $this->getPersonilTagsForKegiatan($kegiatan, $includeTag);

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
        $lines[] = '_Harap selalu laporkan hasil kegiatan kepada atasan._';
        $lines[] = '_Pesan ini dikirim otomatis saat batas waktu tindak lanjut tercapai._';

        $fallback = implode("\n", $lines);

        $labelLines = [
            $this->formatLabelLine('Kode TL', $kodePengingat),
            $this->formatLabelLine('Perihal', $perihal),
            $this->formatLabelLine('Tanggal', $tanggalLabel),
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
            'perihal' => $perihal,
            'tanggal' => $tanggalLabel,
            'batas_tl' => $deadlineLabel,
            'label_lines' => implode("\n", $labelLines),
            'surat_block' => $suratUrl
                ? $this->formatTemplateBlock(['📎 Surat (PDF):', $suratUrl])
                : '',
            'surat_url' => $suratUrl ?? '',
            'lampiran_block' => $lampiranUrl
                ? $this->formatTemplateBlock(['📎 Lampiran Surat:', $lampiranUrl])
                : '',
            'lampiran_url' => $lampiranUrl ?? '',
            'disposisi_block' => $this->formatTemplateBlock($disposisiLines),
            'disposisi_tags' => ! empty($dispositionTags) ? implode(' ', $dispositionTags) : '',
            'personil_tags' => ! empty($personilTags) ? implode(' ', $personilTags) : '',
            'balasan_line' => $this->formatTemplateLine(
                '_Balas pesan ini dengan *TL-' . $kegiatan->id . ' selesai* jika sudah menyelesaikan TL_'
            ),
            'footer' => implode("\n", [
                '_Harap selalu laporkan hasil kegiatan kepada atasan._',
                '_Pesan ini dikirim otomatis saat batas waktu tindak lanjut tercapai._',
            ]),
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('tindak_lanjut_reminder', $data, $fallback);
    }

    protected function formatTindakLanjutDeadlineLabel(Kegiatan $kegiatan): string
    {
        $deadline = $kegiatan->batas_tindak_lanjut ?? $kegiatan->tindak_lanjut_deadline;

        if ($deadline) {
            return $deadline
                ->locale('id')
                ->isoFormat('dddd, D MMMM Y HH:mm') . ' WIB';
        }

        if ($kegiatan->tindak_lanjut_deadline_label) {
            return $kegiatan->tindak_lanjut_deadline_label;
        }

        return '-';
    }

    protected function getDispositionTags(bool $includeTag = true): array
    {
        $roles = [
            'Camat Watumalang',
            'Sekretaris Kecamatan Watumalang',
        ];

        return Personil::query()
            ->whereIn('jabatan', $roles)
            ->get(['no_wa', 'jabatan', 'nama'])
            ->map(fn (Personil $personil) => $this->formatPersonilTag($personil, true, $includeTag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function getPersonilTagsForKegiatan(Kegiatan $kegiatan, bool $includeTag = true): array
    {
        $personils = $kegiatan->personils ?? collect();

        if ($personils->isEmpty()) {
            return [];
        }

        return $personils
            ->map(fn (Personil $personil) => $this->formatPersonilTag($personil, true, $includeTag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function formatLabelLine(string $label, string $value): string
    {
        return sprintf('%-14s: %s', $label, $value);
    }

    /**
     * @return array<int, string>
     */
    protected function formatDisposisiWrappedLines(string $label, string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $prefix = sprintf('%-14s: ', $label);

        return $this->wrapLine($prefix, $value);
    }

    protected function formatDisposisiTemplateLine(string $label, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $prefix = sprintf('%-14s: ', $label);

        return $this->formatTemplateWrappedLine($prefix, $value);
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

    /**
     * @return array<int, string>
     */
    protected function wrapLine(string $prefix, string $value, int $width = self::WA_WRAP_WIDTH): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $prefixLen = strlen($prefix);
        $available = max($width - $prefixLen, 12);
        $wrapped = wordwrap($value, $available, "\n", false);
        $parts = explode("\n", $wrapped);
        $indent = str_repeat(' ', $prefixLen);
        $lines = [];

        foreach ($parts as $index => $part) {
            $lines[] = ($index === 0 ? $prefix : $indent) . $part;
        }

        return $lines;
    }

    protected function formatTemplateWrappedLine(string $prefix, string $value): string
    {
        $lines = $this->wrapLine($prefix, $value);

        if (empty($lines)) {
            return '';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<int, string>
     */
    protected function buildKeteranganLines(string $keterangan): array
    {
        $keterangan = trim($keterangan);

        if ($keterangan === '') {
            return [];
        }

        $lines = ['   📝 Keterangan:'];

        return array_merge($lines, $this->wrapLine('      ', $keterangan));
    }

    protected function resolveDisposisiGreeting(): string
    {
        $hour = (int) now()->format('G');

        if ($hour >= 4 && $hour < 11) {
            return 'Selamat Pagi';
        }

        if ($hour >= 11 && $hour < 15) {
            return 'Selamat Siang';
        }

        if ($hour >= 15 && $hour < 18) {
            return 'Selamat Sore';
        }

        return 'Selamat Malam';
    }

    protected function resolveDisposisiNama(Personil $personil): string
    {
        $nama = trim((string) ($personil->nama ?? ''));

        if ($nama !== '') {
            return $nama;
        }

        return trim((string) ($personil->jabatan ?? ''));
    }

    protected function resolveDisposisiSapaan(Personil $personil): string
    {
        $nama = $this->resolveDisposisiNama($personil);

        return $nama !== '' ? 'Bapak/Ibu ' . $nama : 'Bapak/Ibu';
    }


    /**
     * @param iterable<Personil> $personils
     * @return array<int, string>
     */
    protected function buildPersonilLines(
        iterable $personils,
        bool $includeTag = true,
        int $width = self::WA_WRAP_WIDTH,
        string $baseIndent = '      '
    ): array {
        $lines = [];
        $index = 1;

        foreach ($personils as $personil) {
            $nama = trim((string) ($personil->nama ?? ''));

            if ($nama === '') {
                continue;
            }

            $prefix = $baseIndent . $index . '. ';
            $lines = array_merge($lines, $this->wrapLine($prefix, $nama, $width));

            if ($includeTag) {
                $mention = $this->formatMention($personil->no_wa);
                if ($mention) {
                    $mentionIndent = $baseIndent . str_repeat(' ', strlen((string) $index) + 2);
                    $lines[] = $mentionIndent . $mention;
                }
            }

            $index++;
        }

        return $lines;
    }

    /**
     * @param iterable<Personil> $personils
     */
    protected function buildPersonilListRaw(iterable $personils, bool $includeTag = true): string
    {
        $lines = $this->buildPersonilLines($personils, $includeTag, self::WA_WRAP_WIDTH, '');

        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * @param iterable<Personil> $personils
     */
    protected function buildPersonilNamesRaw(iterable $personils): string
    {
        $names = [];

        foreach ($personils as $personil) {
            $name = trim((string) ($personil->nama ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    /**
     * @return array<int, string>
     */
    protected function getPersonilMentions(Kegiatan $kegiatan): array
    {
        $personils = $kegiatan->personils ?? collect();

        if ($personils->isEmpty()) {
            return [];
        }

        return $personils
            ->map(fn (Personil $personil) => $this->formatMention($personil->no_wa))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function formatPersonilTag(Personil $personil, bool $withJabatan = false, bool $includeTag = true): ?string
    {
        $mention = $this->formatMention($personil->no_wa);
        $name = trim((string) $personil->nama);
        $jabatan = trim((string) $personil->jabatan);

        if (! $includeTag) {
            if ($name === '' && $jabatan === '') {
                return null;
            }

            if ($withJabatan && $jabatan !== '') {
                return $name !== '' ? $name . ' (' . $jabatan . ')' : $jabatan;
            }

            return $name !== '' ? $name : $jabatan;
        }

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
            $raw = trim((string) ($number ?? ''));
            if ($raw === '') {
                continue;
            }

            if (str_contains($raw, '@lid')) {
                $normalized = $this->resolveLidNumber($raw);
            } else {
                $normalized = $this->normalizePhone($raw);
            }

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
    public function formatGroupRekapMessage(iterable $kegiatans): string
    {
        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if (method_exists($items, 'loadMissing')) {
            $items->loadMissing('personils');
        }

        return $this->buildGroupMessage($items);
    }

    public function sendDisposisiNotification(Kegiatan $kegiatan, Personil $personil): bool
    {
        $number = trim((string) ($personil->no_wa ?? ''));
        if ($number === '') {
            return false;
        }

        $message = $this->formatDisposisiNotificationMessage($kegiatan, $personil);
        $result = $this->sendPersonalText([$number], $message);

        return (bool) ($result['success'] ?? false);
    }

    protected function formatDisposisiNotificationMessage(Kegiatan $kegiatan, Personil $personil): string
    {
        $greeting = $this->resolveDisposisiGreeting();
        $sapaan = $this->resolveDisposisiSapaan($personil);
        $nama = $this->resolveDisposisiNama($personil);

        $kegiatanName = trim((string) ($kegiatan->nama_kegiatan ?? ''));
        if ($kegiatanName === '') {
            $kegiatanName = '-';
        }

        $tanggalLabel = trim((string) ($kegiatan->tanggal_label ?? ''));
        if ($tanggalLabel === '') {
            $tanggalLabel = $kegiatan->tanggal
                ? $kegiatan->tanggal->locale('id')->isoFormat('dddd, D MMMM Y')
                : '-';
        }

        $tempat = trim((string) ($kegiatan->tempat ?? ''));
        if ($tempat === '') {
            $tempat = '-';
        }

        $keterangan = trim((string) ($kegiatan->keterangan ?? ''));

        $deadline = $kegiatan->batas_tindak_lanjut ?? $kegiatan->tindak_lanjut_deadline;
        $batasTl = '';
        if ($deadline || $kegiatan->tindak_lanjut_deadline_label) {
            $batasTl = $this->formatTindakLanjutDeadlineLabel($kegiatan);
        }

        $suratUrl = $this->getShortSuratUrl($kegiatan);

        $keteranganLine = $keterangan !== ''
            ? $this->formatDisposisiTemplateLine('Keterangan', $keterangan)
            : '';
        $batasTlLine = $batasTl !== ''
            ? $this->formatDisposisiTemplateLine('Batas TL', $batasTl)
            : '';
        $suratLine = $suratUrl
            ? $this->formatDisposisiTemplateLine('Link surat', $suratUrl)
            : '';

        $data = [
            'greeting' => $greeting,
            'sapaan' => $sapaan,
            'nama' => $nama,
            'kegiatan' => $kegiatanName,
            'tanggal' => $tanggalLabel,
            'tempat' => $tempat,
            'keterangan_raw' => $keterangan,
            'batas_tl' => $batasTl,
            'surat_url' => $suratUrl ?? '',
            'kegiatan_line' => $this->formatDisposisiTemplateLine('Kegiatan', $kegiatanName),
            'tanggal_line' => $this->formatDisposisiTemplateLine('Hari/tanggal', $tanggalLabel),
            'tempat_line' => $this->formatDisposisiTemplateLine('Tempat', $tempat),
            'keterangan_line' => $keteranganLine,
            'batas_tl_line' => $batasTlLine,
            'surat_line' => $suratLine,
            'footer' => '_Harap laporkan hasil kegiatan kepada Pimpinan_',
        ];

        $lines = [];
        $lines[] = $greeting . ' ' . $sapaan . '. Anda telah mendapatkan disposisi agenda berikut:';
        $lines = array_merge($lines, $this->formatDisposisiWrappedLines('Kegiatan', $kegiatanName));
        $lines = array_merge($lines, $this->formatDisposisiWrappedLines('Hari/tanggal', $tanggalLabel));
        $lines = array_merge($lines, $this->formatDisposisiWrappedLines('Tempat', $tempat));

        if ($keterangan !== '') {
            $lines = array_merge($lines, $this->formatDisposisiWrappedLines('Keterangan', $keterangan));
        }

        if ($batasTl !== '') {
            $lines = array_merge($lines, $this->formatDisposisiWrappedLines('Batas TL', $batasTl));
        }

        if ($suratUrl) {
            $lines = array_merge($lines, $this->formatDisposisiWrappedLines('Link surat', $suratUrl));
        }

        $lines[] = '';
        $lines[] = '_Harap laporkan hasilnya kepada Pimpinan_';

        $fallback = implode("\n", $lines);

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('disposisi_personil', $data, $fallback);
    }

    protected function buildGroupMessage(iterable $kegiatans): string
    {
        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);
        $items = $items->sortBy('tanggal');
        $includeTag = $this->includePersonilTagForTemplate('group_rekap');

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
            $lines = array_merge(
                $lines,
                $this->wrapLine('   ⏰ ', (string) ($kegiatan->waktu ?? '-'))
            );
            $lines = array_merge(
                $lines,
                $this->wrapLine('   📍 ', (string) ($kegiatan->tempat ?? '-'))
            );
            $lines[] = '';

            // Personil (Penerima Disposisi)
            $personils = $kegiatan->personils ?? collect();

            if ($personils->isNotEmpty()) {
                $lines[] = '   👥 Penerima Disposisi:';
                $lines = array_merge($lines, $this->buildPersonilLines($personils, $includeTag));

                $lines[] = '';
            }

            // KETERANGAN (hanya kalau diisi)
            $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
            if ($keterangan !== '') {
                $lines = array_merge($lines, $this->buildKeteranganLines($keterangan));
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
        $lines[] = 'Harap selalu laporkan hasil kegiatan kepada atasan.';
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
            'footer' => implode("\n", [
                'Harap selalu laporkan hasil kegiatan kepada atasan.',
                'Pesan ini dikirim otomatis dari sistem agenda kantor.',
            ]),
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
            $lines = array_merge(
                $lines,
                $this->wrapLine(' *Tanggal*     : ', (string) ($kegiatan->tanggal_label ?? '-'))
            );
            $lines = array_merge(
                $lines,
                $this->wrapLine(' *Waktu*       : ', (string) ($kegiatan->waktu ?? '-'))
            );
            $lines = array_merge(
                $lines,
                $this->wrapLine(' *Tempat*      : ', (string) ($kegiatan->tempat ?? '-'))
            );
            $lines[] = '';
            $lines[] = '';
            $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
            if ($keterangan !== '') {
                $lines = array_merge($lines, $this->buildKeteranganLines($keterangan));
                $lines[] = '';
            }
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

        $includeTag = $this->includePersonilTagForTemplate('group_belum_disposisi');
        $leadershipTags = $this->getPersonilTagsByJabatan([
            'Camat Watumalang',
            'Sekretaris Kecamatan Watumalang',
        ], $includeTag);

        if (! empty($leadershipTags)) {
            $lines[] = '';
            $lines[] = '*Mohon petunjuk/arahan disposisi:*';
            $lines[] = implode(' ', $leadershipTags);
        }

        $lines[] = '';
        $lines[] = '_Harap selalu laporkan hasil kegiatan kepada atasan._';
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
            'leadership_tags' => ! empty($leadershipTags) ? implode(' ', $leadershipTags) : '',
            'footer' => implode("\n", [
                '_Harap selalu laporkan hasil kegiatan kepada atasan._',
                '_Pesan ini dikirim otomatis dari sistem agenda kantor._',
            ]),
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('group_belum_disposisi', $data, $fallback);
    }

    /**
     * @param iterable<Kegiatan> $kegiatans
     */
    public function formatGroupBelumDisposisiMessage(iterable $kegiatans): string
    {
        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if (method_exists($items, 'loadMissing')) {
            $items->loadMissing('personils');
        }

        return $this->buildGroupMessageBelumDisposisi($items);
    }

    protected function getPersonilTagsByJabatan(array $jabatanList, bool $includeTag = true): array
    {
        $personils = Personil::query()
            ->whereIn('jabatan', $jabatanList)
            ->get(['nama', 'no_wa', 'jabatan']);

        $tags = [];

        foreach ($personils as $personil) {
            if (! $includeTag) {
                $name = trim((string) ($personil->nama ?? ''));
                $jabatan = trim((string) ($personil->jabatan ?? ''));

                if ($name !== '') {
                    $tags[] = $jabatan !== '' ? $name . ' - ' . $jabatan : $name;
                } elseif ($jabatan !== '') {
                    $tags[] = $jabatan;
                }

                continue;
            }

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
        $includeTag = $this->includePersonilTagForTemplate('agenda_group');
        $participants = $this->formatParticipantsShort($kegiatan, false);
        $notes = trim((string) ($kegiatan->keterangan ?? ''));
        $suratUrl = $this->getShortSuratUrl($kegiatan);
        $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);
        $mentions = $includeTag ? $this->getPersonilMentions($kegiatan) : [];
        $mentionLine = ! empty($mentions)
            ? $this->formatTemplateLine('      ' . implode(' ', $mentions))
            : '';
        $mentionsRaw = ! empty($mentions) ? implode(' ', $mentions) : '';
        $personilListRaw = $this->buildPersonilListRaw($kegiatan->personils ?? collect(), $includeTag);

        $lines[] = '#1 ' . ($title !== '' ? $title : '-');
        $lines = array_merge(
            $lines,
            $this->wrapLine('   ⏰ ', $time !== '' ? $time : '-')
        );
        $lines = array_merge(
            $lines,
            $this->wrapLine('   📍 ', $place !== '' ? $place : '-')
        );

        if ($participants !== '') {
            $lines = array_merge($lines, $this->wrapLine('   👥 ', $participants));
        }

        if ($includeTag) {
            $mentions = $this->getPersonilMentions($kegiatan);
            if (! empty($mentions)) {
                $lines[] = '      ' . implode(' ', $mentions);
            }
        }

        if ($notes !== '') {
            $lines = array_merge($lines, $this->wrapLine('   📝 ', $notes));
        }

        if ($suratUrl) {
            $lines[] = '   📎 Surat: ' . $suratUrl;
        }

        if ($lampiranUrl) {
            $lines[] = '   📎 Lampiran: ' . $lampiranUrl;
        }

        $lines[] = '';
        $lines[] = 'Harap selalu laporkan hasil kegiatan kepada atasan.';
        $lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

        $fallback = implode("\n", $lines);

        $data = [
            'tanggal_header' => $headerDate,
            'judul' => $title !== '' ? $title : '-',
            'waktu' => $time !== '' ? $time : '-',
            'tempat' => $place !== '' ? $place : '-',
            'peserta_line' => $participants !== ''
                ? $this->formatTemplateWrappedLine('   👥 ', $participants) . $mentionLine
                : '',
            'peserta_raw' => $participants,
            'mentions_raw' => $mentionsRaw,
            'mentions_line' => $mentionLine,
            'personil_list_raw' => $personilListRaw,
            'personil_block' => $this->buildPersonilBlock($kegiatan, $includeTag),
            'keterangan_line' => $notes !== ''
                ? $this->formatTemplateWrappedLine('   📝 ', $notes)
                : '',
            'keterangan_raw' => $notes,
            'surat_line' => $this->formatTemplateLine($suratUrl ? '   📎 Surat: ' . $suratUrl : ''),
            'surat_url' => $suratUrl ?? '',
            'lampiran_line' => $this->formatTemplateLine($lampiranUrl ? '   📎 Lampiran: ' . $lampiranUrl : ''),
            'lampiran_url' => $lampiranUrl ?? '',
            'footer' => implode("\n", [
                'Harap selalu laporkan hasil kegiatan kepada atasan.',
                'Pesan ini dikirim otomatis dari sistem agenda kantor.',
            ]),
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
    protected function formatParticipantsShort(Kegiatan $kegiatan, bool $includeTag = true): string
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

            if ($includeTag && $mention) {
                $tags[] = $mention;
            } elseif ($nama !== '') {
                $tags[] = $nama;
            }
        }

        $parts = array_unique(array_filter(array_merge($categoryLabels, $tags)));

        return implode(', ', $parts);
    }

    protected function buildPersonilBlock(Kegiatan $kegiatan, bool $includeTag = true): string
    {
        $personils = $kegiatan->personils ?? collect();
        if ($personils->isEmpty()) {
            return '';
        }

        $lines = ['   👥 Penerima Disposisi:'];
        $lines = array_merge($lines, $this->buildPersonilLines($personils, $includeTag));

        return $this->formatTemplateInlineBlock($lines);
    }

    protected function buildGroupAgendaList(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '(Tidak ada agenda pada hari ini.)';
        }

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);
        $meta = $templateService->metaFor('group_rekap');
        $includeTag = $templateService->includePersonilTag('group_rekap', true);
        $itemTemplate = trim((string) ($meta['item_template'] ?? ''));
        $separator = (string) ($meta['item_separator'] ?? "\n\n");

        if ($itemTemplate !== '') {
            $rendered = [];
            $no = 1;

            /** @var \App\Models\Kegiatan $kegiatan */
            foreach ($items as $kegiatan) {
                $personilBlock = $this->buildPersonilBlock($kegiatan, $includeTag);
                $personilListRaw = $this->buildPersonilListRaw($kegiatan->personils ?? collect(), $includeTag);
                $personilNamesRaw = $this->buildPersonilNamesRaw($kegiatan->personils ?? collect());
                $personilMentionsRaw = $includeTag
                    ? implode(' ', $this->getPersonilMentions($kegiatan))
                    : '';

                $keteranganLines = [];
                $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
                if ($keterangan !== '') {
                $keteranganLines = $this->buildKeteranganLines($keterangan);
                }

                $suratUrl = $this->getShortSuratUrl($kegiatan);
                $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);

                $data = [
                    'no' => (string) $no,
                    'judul' => (string) ($kegiatan->nama_kegiatan ?? '-'),
                    'waktu' => (string) ($kegiatan->waktu ?? '-'),
                    'tempat' => (string) ($kegiatan->tempat ?? '-'),
                    'personil_block' => $personilBlock,
                    'personil_list_raw' => $personilListRaw,
                    'personil_names_raw' => $personilNamesRaw,
                    'personil_mentions_raw' => $personilMentionsRaw,
                    'keterangan_block' => $this->formatTemplateInlineBlock($keteranganLines),
                    'keterangan_raw' => $keterangan,
                    'surat_line' => $this->formatTemplateLine(
                        $suratUrl ? '   📎 Link Surat: ' . $suratUrl : ''
                    ),
                    'surat_url' => $suratUrl ?? '',
                    'lampiran_line' => $this->formatTemplateLine(
                        $lampiranUrl ? '   📎 Lampiran: ' . $lampiranUrl : ''
                    ),
                    'lampiran_url' => $lampiranUrl ?? '',
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
            $lines = array_merge(
                $lines,
                $this->wrapLine('   ⏰ ', (string) ($kegiatan->waktu ?? '-'))
            );
            $lines = array_merge(
                $lines,
                $this->wrapLine('   📍 ', (string) ($kegiatan->tempat ?? '-'))
            );
            $lines[] = '';

            $personils = $kegiatan->personils ?? collect();

            if ($personils->isNotEmpty()) {
                $lines[] = '   👥 Penerima Disposisi:';
                $lines = array_merge($lines, $this->buildPersonilLines($personils, $includeTag));

                $lines[] = '';
            }

            $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
            if ($keterangan !== '') {
                $lines = array_merge($lines, $this->buildKeteranganLines($keterangan));
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
        $includeTag = $templateService->includePersonilTag('group_belum_disposisi', true);
        $itemTemplate = trim((string) ($meta['item_template'] ?? ''));
        $separator = (string) ($meta['item_separator'] ?? "\n\n");
        $leadershipTags = $this->getPersonilTagsByJabatan([
            'Camat Watumalang',
            'Sekretaris Kecamatan Watumalang',
        ], $includeTag);

        if ($itemTemplate !== '') {
            $items->loadMissing('personils');
            $rendered = [];
            $no = 1;

            /** @var \App\Models\Kegiatan $kegiatan */
            foreach ($items as $kegiatan) {
                if ((bool) ($kegiatan->perlu_tindak_lanjut ?? false)) {
                    $rendered[] = $this->buildBelumDisposisiTindakLanjutBlock($kegiatan, $leadershipTags);
                    continue;
                }

                $suratUrl = $this->getShortSuratUrl($kegiatan);
                $keteranganLines = [];
                $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
                if ($keterangan !== '') {
                $keteranganLines = $this->buildKeteranganLines($keterangan);
                }
                $personilListRaw = $this->buildPersonilListRaw($kegiatan->personils ?? collect(), $includeTag);
                $personilNamesRaw = $this->buildPersonilNamesRaw($kegiatan->personils ?? collect());
                $personilMentionsRaw = $includeTag
                    ? implode(' ', $this->getPersonilMentions($kegiatan))
                    : '';
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
                    'keterangan_block' => $this->formatTemplateInlineBlock($keteranganLines),
                    'keterangan_raw' => $keterangan,
                    'surat_block' => $suratBlock,
                    'surat_url' => $suratUrl ?? '',
                    'personil_block' => $this->buildPersonilBlock($kegiatan, $includeTag),
                    'personil_list_raw' => $personilListRaw,
                    'personil_names_raw' => $personilNamesRaw,
                    'personil_mentions_raw' => $personilMentionsRaw,
                ];

                $rendered[] = $templateService->renderString($itemTemplate, $data);
                $no++;
            }

            $rendered[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';

            return implode($separator, $rendered);
        }

        $lines = [];
        $no = 1;
        $index = 0;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            if ($index > 0) {
                $lines[] = '────────────────────────';
            }

            $index++;

            if ((bool) ($kegiatan->perlu_tindak_lanjut ?? false)) {
                $lines = array_merge(
                    $lines,
                    explode("\n", $this->buildBelumDisposisiTindakLanjutBlock($kegiatan, $leadershipTags))
                );
                continue;
            }

            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';
            $lines = array_merge(
                $lines,
                $this->wrapLine(' *Tanggal*     : ', (string) ($kegiatan->tanggal_label ?? '-'))
            );
            $lines = array_merge(
                $lines,
                $this->wrapLine(' *Waktu*       : ', (string) ($kegiatan->waktu ?? '-'))
            );
            $lines = array_merge(
                $lines,
                $this->wrapLine(' *Tempat*      : ', (string) ($kegiatan->tempat ?? '-'))
            );
            $lines[] = '';
            $lines[] = '';

            $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
            if ($keterangan !== '') {
                $lines = array_merge($lines, $this->buildKeteranganLines($keterangan));
                $lines[] = '';
            }

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
     * @param array<int, string> $leadershipTags
     */
    protected function buildBelumDisposisiTindakLanjutBlock(Kegiatan $kegiatan, array $leadershipTags): string
    {
        $nomorSurat = trim((string) ($kegiatan->nomor ?? ''));
        if ($nomorSurat === '') {
            $nomorSurat = '-';
        }

        $perihal = trim((string) ($kegiatan->nama_kegiatan ?? ''));
        if ($perihal === '') {
            $perihal = '-';
        }

        $tanggalLabel = trim((string) ($kegiatan->tanggal_label ?? ''));
        if ($tanggalLabel === '') {
            $tanggalLabel = '-';
        }

        $keterangan = trim((string) ($kegiatan->keterangan ?? ''));
        if ($keterangan === '') {
            $keterangan = '-';
        }

        $deadlineLabel = $this->formatTindakLanjutDeadlineLabel($kegiatan);
        $suratUrl = $this->getShortSuratUrl($kegiatan);
        $lampiranUrl = $this->getLampiranUrl($kegiatan->lampiran_surat ?? null);

        $lines = [
            '*MOHON DISPOSISI — SURAT PERLU TL*',
            '',
            $this->formatLabelLine('Nomor Surat', $nomorSurat),
            $this->formatLabelLine('Perihal', $perihal),
            $this->formatLabelLine('Tanggal', $tanggalLabel),
            $this->formatLabelLine('Keterangan', $keterangan),
            $this->formatLabelLine('Batas TL', $deadlineLabel),
            '',
        ];

        if ($suratUrl) {
            $lines[] = '📎 Surat (PDF):';
            $lines[] = $suratUrl;
            $lines[] = '';
        }

        if ($lampiranUrl) {
            $lines[] = '📎 Lampiran:';
            $lines[] = $lampiranUrl;
            $lines[] = '';
        }

        $lines[] = 'Mohon petunjuk penugasan/arahannya.:';
        if (! empty($leadershipTags)) {
            $lines[] = implode(' ', $leadershipTags);
        }

        return trim(implode("\n", $lines));
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

    /**
     * @param iterable<Kegiatan> $kegiatans
     * @param  array<int, int|string>  $groupIds
     * @return array{success: bool, results: array<int, array{success: bool, response: mixed, error: string|null}>}
     */
    public function sendGroupRekapToGroups(iterable $kegiatans, array $groupIds): array
    {
        if (! $this->hasClientCredentials()) {
            return [
                'success' => false,
                'results' => [],
            ];
        }

        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if ($items->isEmpty()) {
            Log::warning('WA Gateway: sendGroupRekapToGroups dipanggil tanpa data kegiatan');

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
            Log::warning('WA Gateway: sendGroupRekapToGroups tidak menemukan grup tujuan', [
                'input_ids' => $rawIds->all(),
            ]);

            return [
                'success' => false,
                'results' => [],
            ];
        }

        if (method_exists($items, 'loadMissing')) {
            $items->loadMissing('personils');
        }

        $message = $this->buildGroupMessage($items);
        $results = [];
        $success = false;

        foreach ($groups as $group) {
            $phone = $this->resolveGroupPhone($group);

            if (! $phone) {
                Log::warning('WA Gateway: ID grup WA tidak tersedia untuk rekap', [
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

 
}
