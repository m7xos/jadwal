<?php

namespace App\Services;

use App\Models\BanprovVerification;
use App\Models\Personil;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BanprovVerificationGenerator
{
    protected string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?: public_path('template/template_verif.docx');
    }

    /**
     * @return array{path:string,url:string}
     */
    public function generate(BanprovVerification $verification): array
    {
        if (! file_exists($this->templatePath)) {
            throw new \RuntimeException('Template verifikasi banprov tidak ditemukan.');
        }

        $data = $this->buildData($verification);
        $output = $this->buildOutputPath($verification);

        copy($this->templatePath, $output['path']);

        $zip = new ZipArchive();
        if ($zip->open($output['path']) !== true) {
            throw new \RuntimeException('Gagal membuka template verifikasi banprov.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException('Template verifikasi banprov tidak valid.');
        }

        $documentXml = $this->fillTemplate($documentXml, $data);

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $output;
    }

    /**
     * @return array<string, string>
     */
    protected function buildData(BanprovVerification $verification): array
    {
        $jumlah = $verification->jumlah ?? null;
        $jumlahFormatted = $jumlah ? number_format((int) $jumlah, 0, ',', '.') : '-';
        $terbilang = $this->formatTerbilang($jumlah);

        $camat = Personil::query()
            ->where('jabatan', 'Camat Watumalang')
            ->first();

        $kasiEkbang = Personil::query()
            ->whereRaw('LOWER(jabatan) LIKE ?', ['%kasi%ekbang%'])
            ->orWhereRaw('LOWER(jabatan_akronim) = ?', ['ekbang'])
            ->first();

        $stafEkbang = Personil::query()
            ->whereRaw('LOWER(jabatan) LIKE ?', ['%staf%ekbang%'])
            ->first();

        return [
            'tahun' => now()->year,
            'no_dpa' => $verification->no_dpa ?: '-',
            'nama_desa' => $verification->desa ?: '-',
            'nama_kec' => $verification->kecamatan ?: '-',
            'nama_kab' => 'Wonosobo',
            'nama_kades' => '-',
            'no_wa_kades' => '-',
            'jenis_kegiatan' => $verification->jenis_kegiatan ?: '-',
            'jumlah' => $jumlahFormatted,
            'terbilang' => $terbilang,
            'nama_camat' => $camat?->nama ?: '-',
            'nip_camat' => $camat?->nip ?: '-',
            'kasi_ekbang' => $kasiEkbang?->nama ?: '-',
            'nip_kasi_ekbang' => $kasiEkbang?->nip ?: '-',
            'staf_ekbang' => $stafEkbang?->nama ?: '-',
            'nip_staf_ekbang' => $stafEkbang?->nip ?: '-',
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function fillTemplate(string $documentXml, array $data): string
    {
        $dom = new \DOMDocument();
        $dom->loadXML($documentXml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $nodes = [];
        foreach ($xpath->query('//w:t') as $node) {
            $nodes[] = $node;
        }

        $count = count($nodes);

        $simpleMap = [
            'no_dpa' => $data['no_dpa'],
            'nama_desa' => $data['nama_desa'],
            'nama_kec' => $data['nama_kec'],
            'nama_kab' => $data['nama_kab'],
            'nama_kades' => $data['nama_kades'],
            'JENIS KEGIATAN' => $data['jenis_kegiatan'],
            'terbilang' => $data['terbilang'],
            'nama_camat' => $data['nama_camat'],
            'nip_camat' => $data['nip_camat'],
            'nip_kasi_ekbang' => $data['nip_kasi_ekbang'],
            'staf_ekbang' => $data['staf_ekbang'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $value = $nodes[$i]->nodeValue ?? '';

            if ($value === 'tahun'
                && $i > 0
                && ($i + 3) < $count
                && ($nodes[$i - 1]->nodeValue ?? '') === '('
                && ($nodes[$i + 1]->nodeValue ?? '') === ' '
                && ($nodes[$i + 2]->nodeValue ?? '') === 'sesuai'
                && ($nodes[$i + 3]->nodeValue ?? '') === ' excel)'
            ) {
                $nodes[$i - 1]->nodeValue = '';
                $nodes[$i]->nodeValue = (string) $data['tahun'];
                $nodes[$i + 1]->nodeValue = '';
                $nodes[$i + 2]->nodeValue = '';
                $nodes[$i + 3]->nodeValue = '';
                continue;
            }

            if ($value === 'No'
                && ($i + 2) < $count
                && ($nodes[$i + 1]->nodeValue ?? '') === 'WA'
                && ($nodes[$i + 2]->nodeValue ?? '') === 'Kades'
            ) {
                $nodes[$i]->nodeValue = $data['no_wa_kades'];
                $nodes[$i + 1]->nodeValue = '';
                $nodes[$i + 2]->nodeValue = '';
                continue;
            }

            if ($value === '(kasi '
                && ($i + 1) < $count
                && ($nodes[$i + 1]->nodeValue ?? '') === 'ekbang'
            ) {
                $nodes[$i]->nodeValue = str_replace('kasi ', $data['kasi_ekbang'], $value);
                $nodes[$i + 1]->nodeValue = '';
                continue;
            }

            if ($value === 'nip'
                && ($i + 1) < $count
                && ($nodes[$i + 1]->nodeValue ?? '') === '_staf_ekbang'
            ) {
                $nodes[$i]->nodeValue = $data['nip_staf_ekbang'];
                $nodes[$i + 1]->nodeValue = '';
                continue;
            }

            if (isset($simpleMap[$value])) {
                $nodes[$i]->nodeValue = $simpleMap[$value];
                continue;
            }

            if (str_contains($value, 'jumlah')) {
                $nodes[$i]->nodeValue = str_replace('jumlah', $data['jumlah'], $value);
            }
        }

        return $dom->saveXML();
    }

    protected function formatTerbilang(?int $amount): string
    {
        if (! $amount) {
            return '-';
        }

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('id', \NumberFormatter::SPELLOUT);
            $result = $formatter->format($amount);

            return is_string($result) && $result !== '' ? $result : (string) $amount;
        }

        return (string) $amount;
    }

    /**
     * @return array{path:string,url:string}
     */
    protected function buildOutputPath(BanprovVerification $verification): array
    {
        $filename = 'verifikasi_banprov_' . $verification->id . '_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.docx';
        $relative = 'banprov-verifikasi/' . $filename;

        Storage::disk('public')->makeDirectory('banprov-verifikasi');

        return [
            'path' => Storage::disk('public')->path($relative),
            'url' => Storage::disk('public')->url($relative),
        ];
    }
}
