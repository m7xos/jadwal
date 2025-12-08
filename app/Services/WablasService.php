<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\Personil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class WablasService
{
    protected string $baseUrl;
    protected string $token;
    protected ?string $secretKey;
    protected string $groupId;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('wablas.base_url', 'https://solo.wablas.com'), '/');
        $this->token     = (string) config('wablas.token', '');
        $this->secretKey = config('wablas.secret_key'); // boleh null / kosong
        $this->groupId   = (string) config('wablas.group_id', '');
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

    protected function client()
    {
        return Http::withHeaders([
                'Authorization' => $this->getAuthHeaderValue(),
                'Content-Type'  => 'application/json',
            ])
            ->withOptions([
                'verify' => false,
            ]);
    }

    protected function getSuratUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $relativeUrl = Storage::disk('public')->url($path);

        return URL::to($relativeUrl);
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

        $relativeUrl = Storage::disk('public')->url($path);

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
        $name = trim((string) $personil->nama);
        $jabatan = trim((string) $personil->jabatan);

        if ($name !== '') {
            $tag = '@' . $name;

            if ($withJabatan && $jabatan !== '') {
                $tag .= ' (' . $jabatan . ')';
            }

            return $tag;
        }

        $mention = $this->formatMention($personil->no_wa);

        if (! $mention) {
            return null;
        }

        if ($withJabatan && $jabatan !== '') {
            return $mention . ' (' . $jabatan . ')';
        }

        return $mention;
    }

    public function sendGroupText(string $message): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $payload = [
            'data' => [
                [
                    'phone' => $this->groupId,
                    'message' => $message,
                    'isGroup' => true,
                ],
            ],
        ];

        try {
            $response = $this->client()->post($this->baseUrl . '/api/v2/send-message', $payload);
        } catch (\Throwable $exception) {
            Log::error('WablasService: HTTP error kirim teks grup', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::error('WablasService: HTTP error kirim teks grup', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();

        Log::info('WablasService: response sendGroupText', [
            'response' => $json,
        ]);

        return (bool) data_get($json, 'status', false);
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
                'error' => 'Konfigurasi Wablas belum lengkap',
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
            Log::error('WablasService: HTTP error kirim personal text', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            Log::error('WablasService: HTTP error kirim personal text', [
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

    public function sendGroupTindakLanjutReminder(Kegiatan $kegiatan): array
    {
        if (! $this->isConfigured()) {
            Log::error('WablasService: konfigurasi belum lengkap untuk pengingat TL', [
                'base_url'  => $this->baseUrl,
                'token_set' => $this->token !== '',
                'group_id'  => $this->groupId,
            ]);

            return [
                'success' => false,
                'error' => 'Konfigurasi Wablas tidak lengkap',
                'response' => null,
            ];
        }

        $message = $this->buildTindakLanjutReminderMessage($kegiatan);

        $payload = [
            'data' => [
                [
                    'phone'   => $this->groupId,
                    'message' => $message,
                    'isGroup' => true,
                ],
            ],
        ];

        try {
            $response = $this->client()->post($this->baseUrl . '/api/v2/send-message', $payload);
        } catch (\Throwable $exception) {
            Log::error('WablasService: HTTP error kirim pengingat TL', [
                'message' => $exception->getMessage(),
                'kegiatan_id' => $kegiatan->id,
            ]);

            return [
                'success' => false,
                'error' => $exception->getMessage(),
                'response' => null,
            ];
        }

        if (! $response->successful()) {
            Log::error('WablasService: HTTP error kirim pengingat TL', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
                'response' => $response->json(),
            ];
        }

        $json = $response->json();

        Log::info('WablasService: response sendGroupTindakLanjutReminder', [
            'response' => $json,
            'kegiatan' => $kegiatan->id,
        ]);

        return [
            'success' => (bool) data_get($json, 'status', false),
            'response' => $json,
            'error' => null,
        ];
    }

    public function sendGroupRekap(iterable $kegiatans): bool
    {
        if (! $this->isConfigured()) {
            Log::error('WablasService: konfigurasi belum lengkap', [
                'base_url'  => $this->baseUrl,
                'token_set' => $this->token !== '',
                'group_id'  => $this->groupId,
            ]);

            return false;
        }

        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if ($items->isEmpty()) {
            Log::warning('WablasService: sendGroupRekap dipanggil tanpa data kegiatan');

            return false;
        }

        $message = $this->buildGroupMessage($items);

        $payload = [
            'data' => [
                [
                    'phone'   => $this->groupId,
                    'message' => $message,
                    'isGroup' => 'true',
                ],
            ],
        ];

        $response = $this->client()
            ->post($this->baseUrl . '/api/v2/send-message', $payload);

        if (! $response->successful()) {
            Log::error('WablasService: HTTP error kirim group', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();

        Log::info('WablasService: response sendGroupRekap', [
            'response' => $json,
        ]);

        return (bool) data_get($json, 'status', false);
    }

    public function sendGroupBelumDisposisi(iterable $kegiatans): bool
    {
        if (! $this->isConfigured()) {
            Log::error('WablasService: konfigurasi belum lengkap untuk sendGroupBelumDisposisi', [
                'base_url'  => $this->baseUrl,
                'token_set' => $this->token !== '',
                'group_id'  => $this->groupId,
            ]);

            return false;
        }

        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);

        if ($items->isEmpty()) {
            Log::info('WablasService: sendGroupBelumDisposisi dipanggil tanpa data kegiatan');

            return false;
        }

        $message = $this->buildGroupMessageBelumDisposisi($items);

        $payload = [
            'data' => [
                [
                    'phone'   => $this->groupId,
                    'message' => $message,
                    'isGroup' => 'true',
                ],
            ],
        ];

        $response = $this->client()
            ->post($this->baseUrl . '/api/v2/send-message', $payload);

        if (! $response->successful()) {
            Log::error('WablasService: HTTP error kirim agenda belum disposisi', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();

        Log::info('WablasService: response sendGroupBelumDisposisi', [
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
            Log::error('WablasService: HTTP error kirim ke personil', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();

        Log::info('WablasService: response sendToPersonils', [
            'response' => $json,
        ]);

        return (bool) data_get($json, 'status', false);
    }
}
