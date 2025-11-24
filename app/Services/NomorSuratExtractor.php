<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class NomorSuratExtractor
{
    /**
     * @param  string  $path  bisa absolute path (C:\...\file.pdf) atau path relatif di disk (surat-undangan/file.pdf)
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
        // Cocok dengan:
        // - 400.10/533/Kesrasos
        // - 400.10/342/DINSOSPMD
        // - 300.2.2/2261/Kesra
        // - 800/489/BKD (PPPK) :contentReference[oaicite:1]{index=1}
        $pattern = '/Nomor\s*[:\.]\s*([0-9A-Za-z.\/-]+)/i';

        if (preg_match($pattern, $text, $matches)) {
            $line = trim($matches[1]);
            // jaga-jaga kalau masih ada line break
            $line = preg_split("/\r\n|\n|\r/", $line)[0] ?? $line;

            return trim($line);
        }

        // ========== POLA 2: "Nomor" baris sendiri, nomor di baris bawah ==========
        // Untuk surat model:
        // Nomor
        // Sifat
        // Lampiran
        // Hal
        // :
        // :
        // :
        // :
        // 600.4.15/2255   (nomor surat)
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
     * Ambil teks HAL / PERIHAL dari file PDF di storage/public.
     * Contoh baris yang dicari:
     *  "HAL : Undangan Rapat Koordinasi ..."
     *  "Perihal: Rapat Koordinasi Penanggulangan Bencana"
     */
    public function extractPerihalFromStoragePath(string $storagePath): ?string
    {
        try {
            $fullPath = Storage::disk('public')->path($storagePath);
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_file($fullPath)) {
            return null;
        }

        try {
            $text = Pdf::getText($fullPath);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $text) {
            return null;
        }

        // Pecah per baris, cari yang mengandung "HAL" / "PERIHAL"
        $lines = preg_split('/\r\n|\r|\n/', $text);

        foreach ($lines as $line) {
            if (preg_match('/\b(?:HAL|PERIHAL)\s*[:.]\s*(.+)$/iu', $line, $m)) {
                $subject = trim($m[1]);
                // rapikan spasi berlebih
                $subject = preg_replace('/\s+/', ' ', $subject);
                // batasi panjang biar nggak terlalu panjang
                return mb_strimwidth($subject, 0, 200, '...');
            }
        }

        return null;
    }

}

