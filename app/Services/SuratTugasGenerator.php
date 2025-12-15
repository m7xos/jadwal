<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\Personil;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class SuratTugasGenerator
{
    protected string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath ?: public_path('template/surat_tugas.docx');
    }

    /**
     * @return array{path:string,url:string}
     */
    public function generate(Kegiatan $kegiatan): array
    {
        $kegiatan->loadMissing('personils');

        $template = $this->prepareTemplateWithPlaceholders();
        $processor = new TemplateProcessor($template);

        $hariTanggal = $kegiatan->tanggal
            ? Carbon::parse($kegiatan->tanggal)
                ->locale('id')
                ->isoFormat('dddd, D MMMM Y')
            : '-';
        $tanggalSurat = now()->locale('id')->isoFormat('D MMMM Y');

        $personils = $kegiatan->personils ?? collect();

        $processor->setValue('nomor_surat', $kegiatan->nomor ?? '-');
        $processor->setValue('hari_tanggal', $hariTanggal);
        $processor->setValue('tanggal_surat', $tanggalSurat);
        $processor->setValue('nama_kegiatan', $kegiatan->nama_kegiatan ?? '-');
        $processor->setValue('tempat', $kegiatan->tempat ?? '-');
        $processor->setValue('personil', $this->formatPersonilList($personils, 'nama'));
        $processor->setValue('nip', $this->formatPersonilList($personils, 'nip'));
        $processor->setValue('jabatan', $this->formatPersonilList($personils, 'jabatan'));

        $camat = Personil::query()
            ->where('jabatan', 'Camat Watumalang')
            ->first();

        $processor->setValue('camat_watumalang', $camat?->nama ?? '-');
        $processor->setValue('nip_camat', $camat?->nip ?? '-');

        $filename = 'surat_tugas_' . $kegiatan->id . '_' . now()->format('Ymd_His') . '_' . Str::random(5) . '.docx';
        $relativePath = 'surat-tugas/' . $filename;

        Storage::disk('public')->makeDirectory('surat-tugas');
        $outputPath = Storage::disk('public')->path($relativePath);

        $processor->saveAs($outputPath);

        return [
            'path' => $outputPath,
            'url' => Storage::disk('public')->url($relativePath),
        ];
    }

    protected function formatPersonilList($personils, string $attribute): string
    {
        $personils = collect($personils);

        if ($personils->isEmpty()) {
            return '-';
        }

        $useNumbering = $personils->count() > 1;

        return $personils
            ->values()
            ->map(function (Personil $personil, int $index) use ($attribute, $useNumbering): string {
                $value = trim((string) ($personil->{$attribute} ?? ''));
                $label = $value !== '' ? $value : '-';

                if ($useNumbering) {
                    return ($index + 1) . '. ' . $label;
                }

                return $label;
            })
            ->implode("\n");
    }

    /**
     * Template asal menggunakan placeholder $(name); ubah ke ${name} agar TemplateProcessor bisa mengganti.
     */
    protected function prepareTemplateWithPlaceholders(): string
    {
        if (! file_exists($this->templatePath)) {
            throw new \RuntimeException('Template surat tugas tidak ditemukan.');
        }

        $tempPath = Storage::disk('local')->path('temp/surat_tugas_' . Str::random(8) . '.docx');
        Storage::disk('local')->makeDirectory('temp');
        copy($this->templatePath, $tempPath);

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            throw new \RuntimeException('Gagal membuka template surat tugas.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException('Template surat tugas tidak valid.');
        }

        $placeholders = [
            'nomor_surat',
            'hari_tanggal',
            'nama_kegiatan',
            'tempat',
            'personil',
            'nip',
            'jabatan',
            'camat_watumalang',
            'nip_camat',
            'tanggal_surat',
        ];

        foreach ($placeholders as $ph) {
            $documentXml = str_replace('$(' . $ph . ')', '${' . $ph . '}', $documentXml);
        }

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $tempPath;
    }
}
