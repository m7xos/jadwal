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
        $text = $this->readPdfText($path);

        if (! $text) {
            return null;
        }

        $lines = $this->splitLines($text);

        // ========== POLA 1: "Nomor : 800/489/BKD" / "No. 800/123/ABC" ==========
        $pattern = '/\b(?:nomor|no)\b\s*[:\.\-]?\s*([0-9A-Za-z.\/-][0-9A-Za-z.\/-\s]{0,50})/iu';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $normalized = $this->normalizeNomor($match[1] ?? '');

                if ($normalized) {
                    return $normalized;
                }
            }
        }

        // ========== POLA 2: "Nomor" baris sendiri, nomor di baris bawah ==========
        $skipWords = [
            'nomor',
            'no',
            'sifat',
            'lampiran',
            'hal',
            'perihal',
            ':',
            'nomor:',
            'nomor.',
            'nomor-',
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $current = trim($lines[$i]);

            if ($current === '') {
                continue;
            }

            if (preg_match('/^\s*(nomor|no)\b/iu', $current)) {
                // Lihat beberapa baris setelah "Nomor"
                for ($j = $i + 1; $j < min($i + 10, count($lines)); $j++) {
                    $candidate = trim($lines[$j]);

                    if ($candidate === '') {
                        continue;
                    }

                    if (in_array(strtolower($candidate), $skipWords, true)) {
                        continue;
                    }

                    $normalized = $this->normalizeNomor($candidate);

                    if ($normalized) {
                        return $normalized;
                    }
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
        $text = $this->readPdfText($path);

        if (! $text) {
            return null;
        }

        $lines = $this->splitLines($text);

        // ========== POLA 1: "Hal : Revisi Undangan ..." / "Perihal: Undangan ..." (1 baris) ==========
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Awalan Hal/Perihal (case-insensitive), boleh ada titik dua / minus
            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-]?\s*(.+)$/iu', $line, $matches)) {
                $normalized = $this->normalizePerihal($matches[2] ?? '');

                if ($normalized) {
                    return $normalized;
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

                    $normalized = $this->normalizePerihal($candidate);

                    if ($normalized) {
                        return $normalized;
                    }
                }
            }
        }

        return null;
    }

    private function readPdfText(string $path): ?string
    {
        if (is_file($path)) {
            $fullPath = $path;
        } else {
            $fullPath = Storage::disk('public')->path($path);
        }

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $text = Pdf::getText($fullPath);

        return $text ?: null;
    }

    private function splitLines(string $text): array
    {
        return preg_split("/\r\n|\n|\r/", $text) ?: [];
    }

    private function normalizeNomor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_split("/\r\n|\n|\r/", $value)[0] ?? $value;
        $value = trim($value, " \t\n\r\0\x0B:.-");
        $value = preg_replace('/\s+/', '', $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/[0-9A-Za-z]/', $value)) {
            return null;
        }

        if (! preg_match('/^[0-9A-Za-z.\/-]+$/', $value)) {
            return null; // mengandung karakter aneh -> abaikan
        }

        return $value;
    }

    private function normalizePerihal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_split("/\r\n|\n|\r/", $value)[0] ?? $value;
        $value = trim($value, " \t\n\r\0\x0B:.-");
        $value = preg_replace('/^[0-9]+[\).]\s*/', '', $value); // buang numbering seperti "1. ..." / "2) ..."
        $value = preg_replace('/^[-•]\s*/u', '', $value); // buang bullet
        $value = preg_replace('/\s+/', ' ', $value);

        if ($value === '') {
            return null;
        }

        return mb_strimwidth($value, 0, 200, '...');
    }
}
