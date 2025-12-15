<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\Personil;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class SppdGenerator
{
    protected string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?: public_path('template/sppd.docx');
    }

    /**
     * @return array{path:string,url:string,is_zip:bool}
     */
    public function generate(Kegiatan $kegiatan): array
    {
        $kegiatan->loadMissing('personils');

        $personils = $kegiatan->personils ?? collect();

        if ($personils->isEmpty()) {
            throw new \RuntimeException('Personil belum diisi untuk agenda ini.');
        }

        $hariTanggal = $kegiatan->tanggal
            ? Carbon::parse($kegiatan->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y')
            : '-';

        $tanggalCetak = now()->locale('id')->isoFormat('D MMMM Y');

        $camat = Personil::query()
            ->where('jabatan', 'Camat Watumalang')
            ->first();

        $template = $this->prepareTemplateWithPlaceholders();

        $outputPaths = [];

        foreach ($personils as $personil) {
            $processor = new TemplateProcessor($template);

            $processor->setValue('personil', $personil->nama ?? '-');
            $processor->setValue('pangkat', $personil->pangkat ?? '-');
            $processor->setValue('golongan', $personil->golongan ?? '-');
            $processor->setValue('nip', $personil->nip ?? '-');
            $processor->setValue('jabatan', $personil->jabatan ?? '-');
            $processor->setValue('nama_kegiatan', $kegiatan->nama_kegiatan ?? '-');
            $processor->setValue('hari_tanggal', $hariTanggal);
            $processor->setValue('tempat', $kegiatan->tempat ?? '-');
            $processor->setValue('tanggal_cetak', $tanggalCetak);
            $processor->setValue('camat_watumalang', $camat?->nama ?? '-');
            $processor->setValue('nip_camat', $camat?->nip ?? '-');

            $filename = 'sppd_' . $kegiatan->id . '_' . $personil->id . '_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.docx';
            $relativePath = 'sppd/' . $filename;
            $outputPath = Storage::disk('public')->path($relativePath);

            Storage::disk('public')->makeDirectory('sppd');

            $processor->saveAs($outputPath);
            $outputPaths[] = $outputPath;
        }

        if (count($outputPaths) === 1) {
            $path = $outputPaths[0];

            return [
                'path' => $path,
                'url' => Storage::disk('public')->url($this->relativeFromPublicPath($path)),
                'is_zip' => false,
            ];
        }

        // Buat ZIP jika lebih dari 1 file
        $zipName = 'sppd_' . $kegiatan->id . '_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.zip';
        $zipRelative = 'sppd/' . $zipName;
        $zipPath = Storage::disk('public')->path($zipRelative);

        $zip = new ZipArchive();
        Storage::disk('public')->makeDirectory('sppd');

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file ZIP SPPD.');
        }

        foreach ($outputPaths as $filePath) {
            $zip->addFile($filePath, basename($filePath));
        }

        $zip->close();

        return [
            'path' => $zipPath,
            'url' => Storage::disk('public')->url($zipRelative),
            'is_zip' => true,
        ];
    }

    protected function prepareTemplateWithPlaceholders(): string
    {
        if (! file_exists($this->templatePath)) {
            throw new \RuntimeException('Template SPPD tidak ditemukan.');
        }

        $tempPath = Storage::disk('local')->path('temp/sppd_' . Str::random(8) . '.docx');
        Storage::disk('local')->makeDirectory('temp');
        copy($this->templatePath, $tempPath);

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            throw new \RuntimeException('Gagal membuka template SPPD.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException('Template SPPD tidak valid.');
        }

        $placeholders = [
            'personil',
            'pangkat',
            'golongan',
            'nip',
            'jabatan',
            'nama_kegiatan',
            'hari_tanggal',
            'tempat',
            'tanggal_cetak',
            'camat_watumalang',
            'nip_camat',
        ];

        foreach ($placeholders as $ph) {
            $documentXml = str_replace('$(' . $ph . ')', '${' . $ph . '}', $documentXml);
        }

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $tempPath;
    }

    protected function relativeFromPublicPath(string $absolutePath): string
    {
        $publicPath = Storage::disk('public')->path('');

        return ltrim(str_replace($publicPath, '', $absolutePath), DIRECTORY_SEPARATOR);
    }
}
