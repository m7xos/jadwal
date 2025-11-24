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

        // ========== POLA 1: "Nomor: 800/489/BKD" dll ==========
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

        return null;
    }

    /**
     * Ambil HAL / PERIHAL dari PDF.
     *
     * @param  string  $path  bisa absolute path atau path relatif di disk 'public'
     */
    public function extractPerihal(string $path): ?string
    {
        // 1. Tentukan full path (logika sama seperti extract())
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

        // Pecah teks per baris
        $lines = preg_split("/\r\n|\n|\r/", $text);

        if (! is_array($lines)) {
            return null;
        }

        // ========== POLA 1: "Hal: Undangan ...." / "Perihal : ..." satu baris ==========
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Hanya cek baris yang DIAWALI Hal/Perihal, untuk menghindari "hal tersebut..."
            if (preg_match('/^(hal|perihal)\b\s*[:\-]?\s*(.+)$/iu', $line, $matches)) {
                $subject = trim($matches[2] ?? '');

                if ($subject !== '') {
                    // rapikan spasi
                    $subject = preg_replace('/\s+/', ' ', $subject);

                    return mb_strimwidth($subject, 0, 200, '...');
                }
            }
        }

        // ========== POLA 2: "Hal" / "Perihal" di satu baris, isi di baris setelahnya ==========
        for ($i = 0; $i < count($lines); $i++) {
            $current = trim($lines[$i]);

            if ($current === '') {
                continue;
            }

            // Baris hanya "Hal" / "Perihal"
            if (preg_match('/^(hal|perihal)\s*$/iu', $current)) {
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
}
