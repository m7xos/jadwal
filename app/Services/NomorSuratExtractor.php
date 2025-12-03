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

        $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text);

        $inlinePatterns = [
            // "Nomor : 800/489/BKD" / "No. : KU.01/123" / "No : B-12/UND"
            '/\bNomor\s*[:\.]\s*([0-9A-Za-z.\/-]{3,})/iu',
            '/\bNo\.?\s*[:\.]\s*([0-9A-Za-z.\/-]{3,})/iu',
        ];

        // ========== POLA 1: "Nomor : 800/489/BKD" dll ==========
        foreach ($inlinePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $line = trim($matches[1]);
                $line = preg_split("/\r\n|\n|\r/", $line)[0] ?? $line;

                if ($this->isNomorSuratCandidate($line)) {
                    return $line;
                }
            }
        }

        // ========== POLA 2: "Nomor" baris sendiri atau diikuti nomor ==========
        $lines = preg_split("/\r\n|\n|\r/", $text);

        if (! is_array($lines)) {
            return null;
        }

        $skipWords = [
            'nomor',
            'no',
            'no.',
            'sifat',
            'lampiran',
            'hal',
            ':',
            'nomor:',
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $current = trim($lines[$i]);

            if ($current === '') {
                continue;
            }

            // Nomor/No bisa diikuti teks di baris yang sama atau baris selanjutnya
            if (preg_match('/^(Nomor|No\.?)\b\s*(?:[:\.]\s*)?(.*)$/iu', $current, $matches)) {
                $inline = trim($matches[2] ?? '');

                if ($inline !== '' && $this->isNomorSuratCandidate($inline)) {
                    return $inline;
                }

                // Lihat beberapa baris setelah "Nomor"
                for ($j = $i + 1; $j < min($i + 10, count($lines)); $j++) {
                    $candidate = trim($lines[$j]);

                    if ($candidate === '' || in_array(strtolower($candidate), $skipWords, true)) {
                        continue;
                    }

                    if ($this->isNomorSuratCandidate($candidate)) {
                        return $candidate;
                    }
                }
            }

            // Fallback: jika baris awal dokumen sudah menyerupai nomor surat
            if ($i < 15 && $this->isNomorSuratCandidate($current) && ! in_array(strtolower($current), $skipWords, true)) {
                return $current;
            }
        }

        return null;
    }

    protected function isNomorSuratCandidate(string $value): bool
    {
        $value = preg_replace('/\s+/', ' ', trim($value));

        // Hapus titik/koma di akhir
        $value = rtrim($value, '.,;');

        if (strlen($value) < 3 || strlen($value) > 120) {
            return false;
        }

        return (bool) preg_match('/^[0-9A-Za-z][0-9A-Za-z.\/-]+[0-9A-Za-z]$/', $value);
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

        $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text);

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
                $subject = $this->cleanSubject($matches[2] ?? '');

                if ($subject !== '') {
                    return $subject;
                }
            }
        }

        $stopWords = [
            'nomor', 'no', 'no.', 'lampiran', 'sifat', 'tembusan', 'kepada', 'kpd', 'dari', 'hal', 'perihal',
        ];

        // ========== POLA 2: "Hal" / "Perihal" sendirian, isi baris setelahnya + gabungan beberapa baris ==========
        for ($i = 0; $i < count($lines); $i++) {
            $current = trim($lines[$i]);

            if ($current === '') {
                continue;
            }

            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-]?\s*(.*)$/iu', $current, $matches)) {
                $subjectParts = [];
                $inline = $this->cleanSubject($matches[2] ?? '');

                if ($inline !== '') {
                    $subjectParts[] = $inline;
                }

                for ($j = $i + 1; $j < min($i + 6, count($lines)); $j++) {
                    $candidate = trim($lines[$j]);

                    if ($candidate === '' || $candidate === ':') {
                        if (! empty($subjectParts)) {
                            break;
                        }

                        continue;
                    }

                    if (preg_match('/^('.implode('|', $stopWords).')\b/i', strtolower($candidate))) {
                        break;
                    }

                    $cleaned = $this->cleanSubject($candidate);

                    if ($cleaned !== '') {
                        $subjectParts[] = $cleaned;
                    }

                    if (str_ends_with($cleaned, '.') || str_ends_with($cleaned, ';')) {
                        break;
                    }
                }

                $combined = $this->cleanSubject(implode(' ', $subjectParts));

                if ($combined !== '') {
                    return $combined;
                }
            }
        }

        return null;
    }

    protected function cleanSubject(string $subject): string
    {
        $subject = preg_replace('/\s+/', ' ', trim($subject));
        $subject = preg_replace('/^[0-9]+[).\-]\s+/', '', $subject); // hapus penomoran awal

        return mb_strimwidth($subject, 0, 200, '...');
    }
}
