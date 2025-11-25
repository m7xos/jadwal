<?php

namespace App\Services;

use App\Models\Kegiatan;
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
        // Kalau secret key diisi, pakai "token.secret"
        // Kalau tidak, pakai token saja (beberapa device Solo Wablas pakai ini)
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
                // kalau SSL sudah rapi, boleh dihapus verify=false
                'verify' => false,
            ]);
    }

    /**
     * Buat URL publik ke surat undangan (PDF) di storage/public.
     * (Masih disimpan kalau suatu saat ingin pakai direct link.)
     */
    protected function getSuratUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // path relatif di disk 'public' -> /storage/...
        $relativeUrl = Storage::disk('public')->url($path);

        // jadikan absolute URL: https://domainmu/storage/...
        return URL::to($relativeUrl);
    }

    /**
     * Short-link ke surat undangan per kegiatan, misal: https://domain/u/5
     */
    protected function getShortSuratUrl(?Kegiatan $kegiatan): ?string
    {
        if (! $kegiatan || ! $kegiatan->surat_undangan) {
            return null;
        }

        // route('kegiatan.surat.short', ['kegiatan' => {id}])
        return URL::route('kegiatan.surat.short', ['kegiatan' => $kegiatan->id]);
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

        // HEADER
        $lines[] = '*REKAP AGENDA KEGIATAN KANTOR*';
        $lines[] = '';

        if ($items->isNotEmpty()) {
            // Tanggal + jam rekap
            $lines[] = '📅 Tanggal rekap: ' . now()->format('d-m-Y H:i') . ' WIB';

            // Hari & tanggal agenda yang sedang difilter (diambil dari kegiatan pertama)
            $first = $items->first();
            if ($first && $first->tanggal) {
                try {
                    $label = $first->tanggal->locale('id')->isoFormat('dddd, D MMMM Y');
                    $lines[] = '📌 Agenda untuk: *' . $label . '*';
                } catch (\Throwable $e) {
                    // Abaikan error agar tidak menggagalkan pengiriman pesan
                }
            }

            $lines[] = ''; // spasi setelah header
        }

        // ISI AGENDA
        $no = 1;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            if ($no > 1) {
                $lines[] = '────────────────────────';
            }

            // Judul kegiatan
            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';

            // Detail utama (format ringkas)
            $lines[] = '📅 ' . ($kegiatan->tanggal_label ?? '-');
            $lines[] = '⏰ ' . ($kegiatan->waktu ?? '-');
            $lines[] = '📍 ' . ($kegiatan->tempat ?? '-');

            // Personil yang ditugaskan
            $personils = $kegiatan->personils ?? collect();
            if ($personils->isNotEmpty()) {
                // Satu baris: nama + jabatan, dipisah dengan "; "
                $lines[] = '👥 ' . $personils->map(function ($p) {
                    $jabatan = $p->jabatan ? ' (' . $p->jabatan . ')' : '';
                    return $p->nama . $jabatan;
                })->implode('; ');

                // Tag nomor WA untuk notifikasi
                $mentionTags = [];

                foreach ($personils as $p) {
                    $rawNo = trim((string) ($p->no_wa ?? ''));
                    if ($rawNo === '') {
                        continue;
                    }

                    // Hapus karakter non-angka
                    $digits = preg_replace('/[^0-9]/', '', $rawNo) ?? '';
                    if ($digits === '') {
                        continue;
                    }

                    // Normalisasi: 08xxxxx -> 628xxxxx
                    if (substr($digits, 0, 1) === '0') {
                        $digits = '62' . substr($digits, 1);
                    }

                    $mentionTags[] = '@' . $digits;
                }

                if (! empty($mentionTags)) {
                    $lines[] = '🔔 ' . implode(' ', $mentionTags);
                }
            } else {
                $lines[] = '👥 -';
            }

            // Short-link surat undangan
            $suratUrl = $this->getShortSuratUrl($kegiatan);
            if ($suratUrl) {
                $lines[] = '📎 ' . $suratUrl;
            }

            $lines[] = ''; // spasi antar agenda
            $no++;
        }

        // FOOTER
        $lines[] = '_Pesan ini dikirim otomatis dari sistem agenda kantor._';

        return implode("\n", $lines);
    }

    /**
     * Format pesan ringkas untuk agenda yang BELUM disposisi.
     *
     * Hanya menampilkan: nomor surat, nama kegiatan, waktu, tempat, short-link surat.
     *
     * @param iterable<Kegiatan> $kegiatans
     */
    protected function buildGroupMessageBelumDisposisi(iterable $kegiatans): string
    {
        $items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);
        $items = $items->sortBy('tanggal');

        $lines = [];

        $lines[] = '*AGENDA MENUNGGU DISPOSISI PIMPINAN*';
        $lines[] = '';
        $lines[] = 'Berikut daftar kegiatan yang belum mendapatkan disposisi pimpinan:';
        $lines[] = '';

        $no = 1;

        /** @var \App\Models\Kegiatan $kegiatan */
        foreach ($items as $kegiatan) {
            if ($no > 1) {
                $lines[] = '────────────────────────';
            }

            $lines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';
            //$lines[] = '🆔 *Nomor Surat* : ' . ($kegiatan->nomor ?? '-');
            $lines[] = ' *Waktu*       : ' . ($kegiatan->waktu ?? '-');
            $lines[] = ' *Tempat*      : ' . ($kegiatan->tempat ?? '-');

            $suratUrl = $this->getShortSuratUrl($kegiatan);
            if ($suratUrl) {
                $lines[] = '📎 *Surat Undangan (PDF)*';
                $lines[] = $suratUrl;
            }

            $lines[] = ''; // spasi antar kegiatan
            $no++;
        }

        if ($no === 1) {
            $lines[] = '_Tidak ada agenda yang berstatus menunggu disposisi._';
        } else {
            $lines[] = '_Mohon tindak lanjut disposisi sesuai kewenangan._';
        }

        $lines[] = '';
        $lines[] = '_Pesan ini dikirim otomatis dari sistem agenda kantor._';

        return implode("\n", $lines);
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
        $lines[] = ($kegiatan->nama_kegiatan ?? '-');
        $lines[] = '';

        $lines[] = '*Nomor Surat*';
        $lines[] = ($kegiatan->nomor ?? '-');
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

        // Short-link surat undangan kalau ada
        $suratUrl = $this->getShortSuratUrl($kegiatan);
        if ($suratUrl) {
            $lines[] = '📎 *Surat Undangan (PDF)*';
            $lines[] = $suratUrl;
            $lines[] = '';
        }

        $lines[] = 'Mohon kehadiran Bapak/Ibu sesuai jadwal di atas.';
        $lines[] = '';
        $lines[] = '_Pesan ini dikirim otomatis. Mohon tidak membalas ke nomor ini._';

        return implode("\n", $lines);
    }

    /**
     * Kirim rekap ke GRUP WA.
     *
     * @param iterable<Kegiatan> $kegiatans
     */
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
                    'phone'   => $this->groupId,   // group id, bukan nomor biasa
                    'message' => $message,
                    'isGroup' => 'true',          // string "true" sesuai docs
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

    /**
     * Kirim ke grup WA: daftar agenda yang BELUM disposisi.
     *
     * @param iterable<Kegiatan> $kegiatans
     */
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
                    'phone'   => $this->groupId,  // ID grup dari config
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

    /**
     * Kirim pesan ke WA seluruh personil yang hadir pada 1 kegiatan.
     */
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
