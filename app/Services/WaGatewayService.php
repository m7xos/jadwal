<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\Group;
use App\Models\Personil;
use App\Models\PersonilCategory;
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

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('wa_gateway.base_url', 'http://localhost:5001'), '/');
        $this->token     = (string) config('wa_gateway.token', '');
        $this->secretKey = config('wa_gateway.secret_key'); // boleh null / kosong
        $this->provider  = (string) config('wa_gateway.provider', 'wa-gateway');
        $this->groupMappings = (array) config('wa_gateway.group_ids', []);
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

        $masterKey = trim((string) config('wa_gateway.key', ''));
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
        

        return implode("\n", $lines);
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

        return implode("\n", $lines);
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

        return implode("\n", $lines);
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

        return implode("\n", $lines);
    }

    /**
     * Bangun pesan agenda untuk dikirim ke beberapa grup WhatsApp.
     *
     * @param  array<int, int|string>  $groupIds
     */
    public function buildAgendaMessageForGroups(Kegiatan $kegiatan, array $groupIds): string
    {
        $kegiatan->loadMissing('personils');

        $personilsDiGrup = $kegiatan->getPersonilUntukGrup($groupIds)->keyBy('id');
        $allPersonils = ($kegiatan->personils ?? collect())->keyBy('id');
        $groups = Group::query()
            ->whereIn('id', $groupIds)
            ->get();

        $lines = [];

        $lines[] = '*UNDANGAN / INFORMASI KEGIATAN*';
        $lines[] = '────────────────────────';
        $lines[] = '';

        if ($groups->isNotEmpty()) {
            $lines[] = '*Grup Tujuan*';
            $lines[] = $groups->pluck('nama')->filter()->implode(', ');
            $lines[] = '';
        }

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

        if ($allPersonils->isNotEmpty()) {
            $lines[] = '*Peserta / Disposisi*';
            $kategoriLabels = PersonilCategory::options();

            $grouped = $allPersonils
                ->groupBy(fn (Personil $p) => $p->kategori ?? 'lainnya')
                ->sortKeys();

            $counter = 1;

            foreach ($grouped as $kategori => $personilsKategori) {
                $labelKategori = $kategoriLabels[$kategori] ?? PersonilCategory::labelFor($kategori);
                $lines[] = $counter . '. ' . $labelKategori;
                $personIndex = 1;
                $useNumbering = $personilsKategori->count() > 1;

                foreach ($personilsKategori as $personil) {
                    $inGroup = $personilsDiGrup->has($personil->id);
                    $mention = $this->formatMention($personil->no_wa);
                    $jabatan = trim((string) ($personil->jabatan ?? ''));
                    $nama = trim((string) ($personil->nama ?? ''));
                    $prefix = $useNumbering ? ($personIndex . '. ') : '- ';

                    if ($inGroup && $mention) {
                        $lines[] = '       ' . $prefix . $mention;
                        if ($jabatan !== '') {
                            $lines[] = '          ' . $jabatan;
                        }
                        $personIndex++;
                        continue;
                    }

                    if ($nama === '') {
                        continue;
                    }

                    $lines[] = '       ' . $prefix . $nama;
                    if ($jabatan !== '') {
                        $lines[] = '          ' . $jabatan;
                    }
                    $personIndex++;
                }

                $lines[] = '';
                $counter++;
            }
        }

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
        $lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

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
