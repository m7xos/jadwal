<?php

namespace App\Services;

use App\Models\Personil;
use App\Models\VehicleAsset;
use App\Models\VehicleTaxSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class SuratKuasaPajakGenerator
{
    protected string $templatePath;

    public function __construct(string $templatePath = null)
    {
        $this->templatePath = $templatePath ?: public_path('template/surat_kuasa.docx');
    }

    /**
     * @param  array<int,string>  $nomorPolisiList
     */
    /**
     * @return array{path:string,url:string}
     */
    public function generate(array $nomorPolisiList): array
    {
        $nomorPolisiList = array_values(array_filter($nomorPolisiList));

        if (empty($nomorPolisiList)) {
            throw new \InvalidArgumentException('Nomor polisi belum dipilih.');
        }

        $assets = VehicleAsset::query()
            ->whereIn('nomor_polisi', $nomorPolisiList)
            ->get();

        if ($assets->isEmpty()) {
            throw new \RuntimeException('Data kendaraan tidak ditemukan.');
        }

        $dataAset = $this->formatDataAset($assets);
        $tanggalSurat = now()->locale('id')->isoFormat('D MMMM Y');

        $camat = Personil::query()
            ->where('jabatan', 'Camat Watumalang')
            ->first();

        $settings = VehicleTaxSetting::current();

        $template = $this->prepareTemplateWithPlaceholders();
        $processor = new TemplateProcessor($template);

        $processor->setValue('data_aset', $dataAset);
        $processor->setValue('tanggal_surat', $tanggalSurat);
        $processor->setValue('camat_watumalang', $camat?->nama ?? '-');
        $processor->setValue('nip_camat', $camat?->nip ?? '-');
        $processor->setValue('pengurus_barang', $settings->resolved_pengurus_barang_nama ?? '-');
        $processor->setValue('nip_pengurus_barang', $settings->resolved_pengurus_barang_nip ?? '-');

        $filename = 'surat_kuasa_pajak_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.docx';
        $relativePath = 'surat-kuasa/' . $filename;
        $outputPath = Storage::disk('public')->path($relativePath);
        Storage::disk('public')->makeDirectory('surat-kuasa');

        $processor->saveAs($outputPath);

        return [
            'path' => $outputPath,
            'url' => Storage::disk('public')->url($relativePath),
        ];
    }

    protected function formatDataAset(Collection $assets): string
    {
        $lines = [];

        foreach ($assets->values() as $index => $asset) {
            $parts = array_filter([
                $asset->merk_type,
                $asset->nomor_polisi,
                $asset->nomor_rangka,
               //$asset->nomor_mesin,
            ], fn ($value) => filled($value));

            $label = implode(', ', $parts);
            $number = $index + 1;

            $lines[] = $number . '. ' . $label;
        }

        // Baris baru agar rapi, tetap satu string untuk placeholder
        return implode("\n", $lines);
    }

    /**
     * Template asal menggunakan placeholder $(name); ubah ke ${name} agar TemplateProcessor bisa mengganti.
     */
    protected function prepareTemplateWithPlaceholders(): string
    {
        if (! file_exists($this->templatePath)) {
            throw new \RuntimeException('Template surat kuasa tidak ditemukan.');
        }

        $tempPath = Storage::disk('local')->path('temp/surat_kuasa_' . Str::random(8) . '.docx');
        Storage::disk('local')->makeDirectory('temp');
        copy($this->templatePath, $tempPath);

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            throw new \RuntimeException('Gagal membuka template surat kuasa.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException('Template surat kuasa tidak valid.');
        }

        $placeholders = [
            'data_aset',
            'tanggal_surat',
            'camat_watumalang',
            'nip_camat',
            'pengurus_barang',
            'nip_pengurus_barang',
        ];

        foreach ($placeholders as $ph) {
            $documentXml = str_replace('$(' . $ph . ')', '${' . $ph . '}', $documentXml);
        }

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $tempPath;
    }
}
