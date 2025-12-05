<?php

namespace App\Services;

use App\Models\KodeSurat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class NomorSuratExtractor
{
    private ?array $kodeSuratPrefixes = null;

    /**
     * Ambil NOMOR SURAT dari PDF.
     *
     * @param  string  $path  bisa absolute path (C:\...\file.pdf)
     *                        atau path relatif di disk 'public' (surat-undangan/file.pdf)
     */
    public function extract(string $path): ?string
    {
        // 1. Tentukan full path
        if (is_file($path)) {
            // Sudah absolute path (temp file / dll)
            $fullPath = $path;
        } else {
            // Anggap path relatif di disk 'public'
            $fullPath = Storage::disk('public')->path($path);
        }

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        // 2. Baca teks dari PDF
        $text = Pdf::getText($fullPath);

        if (! $text) {
            return null;
        }

        // ========== POLA 1: "Nomor : 800/489/BKD" dll ==========
        $pattern = '/Nomor\s*[:\.]\s*([0-9A-Za-z.\/-]+)/i';

        if (preg_match($pattern, $text, $matches)) {
            $line = trim($matches[1]);
            // jaga-jaga kalau masih ada line break
            $line = preg_split("/\r\n|\n|\r/", $line)[0] ?? $line;

            return trim($line);
        }

        // ========== POLA 2: "Nomor" baris sendiri, nomor di baris bawah ==========
        $lines = preg_split("/\r\n|\n|\r/", $text);

        if (! is_array($lines)) {
            return null;
        }

        $skipWords = [
            'nomor',
            'sifat',
            'lampiran',
            'hal',
            ':',
            'nomor:',
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $current = trim($lines[$i]);

            if (preg_match('/^Nomor\b/i', $current)) {
                // Lihat beberapa baris setelah "Nomor"
                for ($j = $i + 1; $j < min($i + 10, count($lines)); $j++) {
                    $candidate = trim($lines[$j]);

                    if ($candidate === '') {
                        continue;
                    }

                    if (in_array(strtolower($candidate), $skipWords, true)) {
                        continue;
                    }

                    // cocokkan pola nomor surat: angka, huruf, titik, slash, minus
                    if (preg_match('/^[0-9A-Za-z.\/-]+$/', $candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        // ========== POLA 3: gunakan kode surat yang tersimpan (prefix sebelum '/') ==========
        $kodePrefixes = $this->getKodeSuratPrefixes();

        if (! empty($kodePrefixes)) {
            $escaped = array_map(fn (string $code) => preg_quote($code, '#'), $kodePrefixes);
            $escaped = array_values(array_filter($escaped));

            if (! empty($escaped)) {
                $patternKode = '#(' . implode('|', $escaped) . ')\s*/\s*([0-9A-Za-z.\-]+)#';

                if (preg_match($patternKode, $text, $matches)) {
                    return trim(($matches[1] ?? '') . '/' . ($matches[2] ?? ''));
                }
            }
        }

        return null;
    }

    /**
     * Ambil HAL / PERIHAL dari PDF.
     *
     * @param  string  $path  bisa absolute path atau path relatif di disk 'public'
     */
    public function extractPerihal(string $path): ?string
    {
        // 1. Tentukan full path
        if (is_file($path)) {
            $fullPath = $path;
        } else {
            $fullPath = Storage::disk('public')->path($path);
        }

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        // 2. Baca teks dari PDF
        $text = Pdf::getText($fullPath);

        if (! $text) {
            return null;
        }

        // Pecah per baris
        $lines = preg_split("/\r\n|\n|\r/", $text);

        if (! is_array($lines)) {
            return null;
        }

        // ========== POLA 1: "Hal : Revisi Undangan ..." / "Perihal: Undangan ..." (1 baris) ==========
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Awalan Hal/Perihal (case-insensitive), boleh ada titik dua / minus
            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-]?\s*(.+)$/iu', $line, $matches)) {
                $subject = trim($matches[2] ?? '');

                if ($subject !== '') {
                    $subject = preg_replace('/\s+/', ' ', $subject);

                    return mb_strimwidth($subject, 0, 200, '...');
                }
            }
        }

        // ========== POLA 2: "Hal" / "Perihal" sendirian, isi baris setelahnya ==========
        for ($i = 0; $i < count($lines); $i++) {
            $current = trim($lines[$i]);

            if ($current === '') {
                continue;
            }

            // Baris hanya "Hal", "Hal:", "Perihal", "Perihal:"
            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-]?\s*$/iu', $current)) {
                for ($j = $i + 1; $j < min($i + 5, count($lines)); $j++) {
                    $candidate = trim($lines[$j]);

                    if ($candidate === '' || $candidate === ':') {
                        continue;
                    }

                    $candidate = preg_replace('/\s+/', ' ', $candidate);

                    return mb_strimwidth($candidate, 0, 200, '...');
                }
            }
        }

        return null;
    }

    /**
     * Ambil tanggal surat yang ditulis dengan pola "Wonosobo, 20 November 2025".
     */
    public function extractTanggal(string $path): ?string
    {
        $fullPath = is_file($path) ? $path : Storage::disk('public')->path($path);

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $text = Pdf::getText($fullPath);

        if (! $text) {
            return null;
        }

        // Cari pola "Wonosobo, 20 November 2025"
        if (preg_match('/Wonosobo\s*,\s*([0-9]{1,2})\s+([A-Za-z]+)\s+([0-9]{4})/i', $text, $matches)) {
            $day = (int) ($matches[1] ?? 0);
            $monthName = strtolower(trim($matches[2] ?? ''));
            $year = (int) ($matches[3] ?? 0);

            $month = $this->parseIndonesianMonth($monthName);

            if ($day > 0 && $month !== null && $year > 0) {
                try {
                    return Carbon::createFromDate($year, $month, $day)->toDateString();
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        return null;
    }

    private function parseIndonesianMonth(string $monthName): ?int
    {
        $map = [
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];

        return $map[$monthName] ?? null;
    }

    private function getKodeSuratPrefixes(): array
    {
        if ($this->kodeSuratPrefixes !== null) {
            return $this->kodeSuratPrefixes;
        }

        $this->kodeSuratPrefixes = KodeSurat::query()
            ->pluck('kode')
            ->filter()
            ->map(fn ($kode) => trim((string) $kode))
            ->filter()
            ->values()
            ->all();

        return $this->kodeSuratPrefixes;
    }
}
