<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class NomorSuratExtractor
{
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

        // ========== POLA 1: "Nomor : 800/489/BKD" / "No. 800/489/BKD" pada baris yang sama ==========
        $patternInline = '/\b(?:Nomor|No)\.?\s*(?:Surat)?\s*[:\-\.]\s*([0-9A-Za-z][0-9A-Za-z.\/-]+(?:\s*[0-9A-Za-z.\/-]+)*)/iu';

        if (preg_match($patternInline, $text, $matches)) {
            $line = trim($matches[1]);
            $line = preg_split("/\r\n|\n|\r/", $line)[0] ?? $line;

            return $this->sanitizeNomor($line);
        }

        // ========== POLA 2: "Nomor" baris sendiri, nomor di baris bawah atau setelah tab ==========
        $lines = $this->normalizeLines($text);

        if (empty($lines)) {
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

        // Cari baris yang ada kata "Nomor" atau "No" lalu ambil kandidat terdekat
        for ($i = 0; $i < count($lines); $i++) {
            $current = $lines[$i];

            if (preg_match('/\bNomor\b|\bNo\b/i', $current)) {
                // Jika di baris yang sama ada angka, ambil yang setelah tanda : atau spasi panjang
                if (preg_match('/\b(?:Nomor|No)\b[^0-9A-Za-z]*([0-9A-Za-z][0-9A-Za-z.\/-]+(?:\s*[0-9A-Za-z.\/-]+)*)/u', $current, $sameLine)) {
                    $candidate = $this->sanitizeNomor($sameLine[1]);
                    if ($candidate) {
                        return $candidate;
                    }
                }

                // Kalau tidak ada di baris yang sama, cek beberapa baris setelahnya
                for ($j = $i + 1; $j < min($i + 8, count($lines)); $j++) {
                    $candidate = $lines[$j];

                    if ($candidate === '' || in_array(strtolower($candidate), $skipWords, true)) {
                        continue;
                    }

                    if ($this->looksLikeDate($candidate)) {
                        continue;
                    }

                    if (preg_match('/^[0-9A-Za-z.\/-]+(?:\s*[0-9A-Za-z.\/-]+)*$/u', $candidate)) {
                        return $this->sanitizeNomor($candidate);
                    }
                }
            }
        }

        // ========== POLA 3: cari kandidat nomor di 20 baris pertama (surat resmi biasanya di atas) ==========
        $topLines = array_slice($lines, 0, 20);
        $best = null;

        foreach ($topLines as $line) {
            if ($line === '' || $this->looksLikeDate($line)) {
                continue;
            }

            if (preg_match('/\b([0-9]{1,4}\/[^\s]{3,}\/[0-9]{2,4})\b/u', $line, $matches)) {
                $candidate = $this->sanitizeNomor($matches[1]);

                if ($candidate && (strlen($candidate) > strlen($best ?? '') || $best === null)) {
                    $best = $candidate;
                }
            }
        }

        return $best;
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

        $lines = $this->normalizeLines($text);

        if (empty($lines)) {
            return null;
        }

        // ========== POLA 1: "Hal : Revisi Undangan ..." / "Perihal: Undangan ..." (1 baris) ==========
        foreach ($lines as $index => $line) {
            // Awalan Hal/Perihal (case-insensitive), boleh ada titik dua / minus
            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-]?\s*(.+)$/iu', $line, $matches)) {
                $subject = $this->mergeContinuationLines($lines, $index, $matches[2]);

                if ($subject !== '') {
                    return $subject;
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

                    $candidate = $this->mergeContinuationLines($lines, $j, $candidate);

                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Buat semua baris lebih rapi: hilangkan spasi ganda, hilangkan karakter non-printable.
     */
    protected function normalizeLines(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];

        return array_values(array_map(function (string $line) {
            $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $line) ?: '';
            $clean = preg_replace('/\s+/', ' ', $clean) ?: '';

            return trim($clean);
        }, $lines));
    }

    /**
     * Hilangkan karakter pengganggu dan batasi panjang nomor.
     */
    protected function sanitizeNomor(?string $candidate): ?string
    {
        if (! $candidate) {
            return null;
        }

        $candidate = trim($candidate, " \t\r\n:.-");
        $candidate = preg_replace('/\s+/', ' ', $candidate) ?: '';

        if ($candidate === '') {
            return null;
        }

        return mb_strimwidth($candidate, 0, 120, '');
    }

    /**
     * Deteksi baris yang lebih mirip tanggal supaya tidak keliru terbaca sebagai nomor surat.
     */
    protected function looksLikeDate(string $line): bool
    {
        $datePatterns = [
            '/\b\d{1,2}\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b/i',
            '/\b\d{1,2}[\-\/]\d{1,2}[\-\/]\d{2,4}\b/',
            '/\b\d{4}\b/',
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gabungkan baris lanjutan jika kalimatnya terpotong dan belum ketemu penanda bagian lain.
     */
    protected function mergeContinuationLines(array $lines, int $startIndex, string $firstPart): string
    {
        $subject = trim(preg_replace('/\s+/', ' ', $firstPart) ?? '');

        // Jika sudah cukup jelas dan ada tanda baca akhir, langsung kembalikan
        if ($subject !== '' && preg_match('/[\.\;\:]$/', $subject) === 0) {
            for ($k = $startIndex + 1; $k < min($startIndex + 3, count($lines)); $k++) {
                $next = trim($lines[$k]);

                if ($next === '' || preg_match('/^(lampiran|tembusan|nomor|tanggal|sifat)\b/i', $next)) {
                    break;
                }

                $subject .= ' ' . $next;

                // Jika sudah berakhir dengan titik atau panjang cukup, hentikan
                if (preg_match('/[\.\!]$/', $next) || strlen($subject) > 200) {
                    break;
                }
            }
        }

        $subject = preg_replace('/\s+/', ' ', $subject) ?: '';

        return mb_strimwidth($subject, 0, 200, '...');
    }
}
