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
        $this->secretKey = config('wablas.secret_key');      // boleh null / kosong
        $this->groupId   = (string) config('wablas.group_id', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' &&
            $this->token !== '' &&
            $this->groupId !== '';
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
            ->withOptions(['verify' => false]); // kalau SSL sudah rapi, boleh dihapus verify=false
    }

    /**
     * Buat URL publik ke surat undangan (PDF) di storage/public.
     */
    protected function getSuratUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // path relatif di disk 'public' → /storage/...
        $relativeUrl = Storage::disk('public')->url($path);

        // jadikan absolute URL: https://domainmu/storage/...
        return URL::to($relativeUrl);
    }

    /**
     * Format pesan rekap untuk banyak kegiatan (untuk grup WA).
     *
     * @param iterable<Kegiatan> $kegiatans
     */
   /**
 * Format pesan rekap untuk banyak kegiatan (untuk grup WA).
 *
 * @param iterable<Kegiatan> $kegiatans
 */
	protected function buildGroupMessage(iterable $kegiatans): string
	{
		$items = $kegiatans instanceof Collection ? $kegiatans : collect($kegiatans);
		$items = $items->sortBy('tanggal');

		$messageLines = [];

		$messageLines[] = '*REKAP AGENDA KEGIATAN KANTOR*';
		$messageLines[] = '';

		if ($items->isNotEmpty()) {
			$messageLines[] = '📅 Tanggal rekap: *' .
				optional($items->first()->tanggal)->format('d-m-Y') . '*';
			$messageLines[] = '';
		}

		$no = 1;

		/** @var \App\Models\Kegiatan $kegiatan */
		foreach ($items as $kegiatan) {
			if ($no > 1) {
				$messageLines[] = '────────────────────────';
			}

			// Judul kegiatan
			$messageLines[] = '*' . $no . '. ' . ($kegiatan->nama_kegiatan ?? '-') . '*';

			// Detail utama
			$messageLines[] = '🆔 *Nomor Surat*  : ' . ($kegiatan->nomor ?? '-');
			$messageLines[] = '📅 *Hari/Tanggal* : ' . ($kegiatan->tanggal_label ?? '-');
			$messageLines[] = '⏰ *Waktu*        : ' . ($kegiatan->waktu ?? '-');
			$messageLines[] = '📍 *Tempat*       : ' . ($kegiatan->tempat ?? '-');

			// Personil
			$personils = $kegiatan->personils ?? collect();
			if ($personils->isNotEmpty()) {
				$messageLines[] = '👥 *Personil Hadir*:';
				foreach ($personils as $p) {
					$jabatan = $p->jabatan ? ' (' . $p->jabatan . ')' : '';
					$messageLines[] = '   • ' . $p->nama . $jabatan;
				}
			} else {
				$messageLines[] = '👥 *Personil Hadir*: -';
			}

			// Keterangan
			if (! empty($kegiatan->keterangan)) {
				$messageLines[] = '📝 *Keterangan*:';
				$messageLines[] = $kegiatan->keterangan;
			}

			// Link surat undangan
			$suratUrl = $this->getSuratUrl($kegiatan->surat_undangan ?? null);
			if ($suratUrl) {
				$messageLines[] = '📎 *Surat Undangan (PDF)*:';
				$messageLines[] = $suratUrl;
			}

			$messageLines[] = ''; // spasi antar agenda
			$no++;
		}

		$messageLines[] = '_Pesan ini dikirim otomatis dari sistem agenda kantor._';

		return implode("\n", $messageLines);
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

		// Link surat undangan kalau ada
		$suratUrl = $this->getSuratUrl($kegiatan->surat_undangan ?? null);
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
