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
     * Normalisasi nomor WA agar bisa ditag di pesan grup.
     */
    protected function formatMention(?string $number): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) ($number ?? '')) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return '@' . $digits;
    }

    /**
     * Ambil tag WA Camat & Sekretaris Kecamatan untuk arahan disposisi.
     */
    protected function getDispositionTags(): array
    {
        $tags = [];

        $targets = [
            ['number' => config('wablas.camat_wa'), 'label' => 'Camat'],
            ['number' => config('wablas.sekcam_wa'), 'label' => 'Sekretaris Kecamatan'],
        ];

        foreach ($targets as $target) {
            $mention = $this->formatMention($target['number']);

            if (! $mention) {
                continue;
            }

            $tags[] = $mention . ' (' . $target['label'] . ')';
        }

        return $tags;
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
						->isoFormat('dddd, D MMMM Y'); // Kamis, 27 November 2025

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
			// Nomor + nama kegiatan
			$lines[] = $no . '. ' . ($kegiatan->nama_kegiatan ?? '-');
			$lines[] = '';

			// Waktu & tempat
			$lines[] = '⏰ ' . ($kegiatan->waktu ?? '-');
			$lines[] = '📍 ' . ($kegiatan->tempat ?? '-');
			$lines[] = '';

			// Personil (Penerima Disposisi)
			$personils = $kegiatan->personils ?? collect();

			if ($personils->isNotEmpty()) {
				$lines[] = '👥 Penerima Disposisi:';

				$i = 1;
				foreach ($personils as $p) {
					$nama = trim((string) ($p->nama ?? ''));

					if ($nama === '') {
						continue;
					}

					// Normalisasi nomor WA -> hanya digit
					$rawNo  = trim((string) ($p->no_wa ?? ''));
					$digits = preg_replace('/[^0-9]/', '', $rawNo) ?? '';

					if ($digits !== '') {
						// 08xxxx -> 628xxxx
						if (substr($digits, 0, 1) === '0') {
							$digits = '62' . substr($digits, 1);
						}

						$tag = ' @' . $digits;
					} else {
						// kalau nomor kosong / tidak valid, tidak ada tag
						$tag = '';
					}

					//       1. NAMA PERSONIL @628xxxx
					$lines[] = '      ' . $i . '. ' . $nama . $tag;
					$i++;
				}

				$lines[] = '';
			}

			// KETERANGAN (hanya kalau diisi)
			$keterangan = trim((string) ($kegiatan->keterangan ?? ''));
			if ($keterangan !== '') {
				$lines[] = '📝 Keterangan:';
				$lines[] = '      ' . $keterangan;
				$lines[] = '';
			}

			// Link surat singkat
			$suratUrl = $this->getShortSuratUrl($kegiatan);
			if ($suratUrl) {
				$lines[] = '📎 Link Surat: ' . $suratUrl;
				$lines[] = '';
			}

			$no++;
		}

		// Kalau tidak ada agenda
		if ($no === 1) {
			$lines[] = '(Tidak ada agenda pada hari ini.)';
			$lines[] = '';
		}

		// Footer "Tanggal rekap"
		$lines[] = 'Tanggal rekap: ' . now()
			->locale('id')
			->translatedFormat('d F Y H:i') . ' WIB';
		$lines[] = '';
		$lines[] = 'Pesan ini dikirim otomatis dari sistem agenda kantor.';

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
        $dispositionTags = $this->getDispositionTags();
        if (! empty($dispositionTags)) {
            $lines[] = 'Arahan disposisi: ' . implode(' ', $dispositionTags) . '.';
            $lines[] = '';
        }
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
            $lines[] = ' *Tanggal*     : ' . ($kegiatan->tanggal_label ?? '-');
            $lines[] = ' *Waktu*       : ' . ($kegiatan->waktu ?? '-');
            $lines[] = ' *Tempat*      : ' . ($kegiatan->tempat ?? '-');
			      $lines[] = '';
            $lines[] = '';
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

    /**
     * Ambil tag nomor WhatsApp berdasarkan jabatan tertentu.
     *
     * @param array<int, string> $jabatanList
     * @return array<int, string>
     */
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
