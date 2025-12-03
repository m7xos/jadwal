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

        $lines = $this->normalizeLines($text);

        if (! $lines) {
            return null;
        }

        // ========== POLA 1: "Nomor : 800/489/BKD" / "No. 800/489/BKD" (1 baris) ==========
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/\b(no\.?|nomor)(\s+surat)?\b\s*[:\.\-–—]?\s*(.+)$/iu', $line, $matches)) {
                $candidate = $this->cleanNomorCandidate($matches[3] ?? '');

                if ($candidate) {
                    return $candidate;
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
            'no:',
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $current = strtolower($lines[$i]);

            if (preg_match('/^no\.?\b|^nomor\b/i', $current)) {
                for ($j = $i + 1; $j < min($i + 10, count($lines)); $j++) {
                    $candidate = $lines[$j];

                    if ($candidate === '') {
                        continue;
                    }

                    if (in_array(strtolower($candidate), $skipWords, true)) {
                        continue;
                    }

                    $candidate = $this->cleanNomorCandidate($candidate);

                    if ($candidate) {
                        return $candidate;
                    }
                }
            }
        }

        // ========== POLA 3: baris awal yang menyerupai nomor surat ==========
        foreach ($lines as $index => $line) {
            if ($index > 50) {
                break; // Fokus di bagian kepala surat saja
            }

            if ($line === '' || preg_match('/\b(lampiran|hal|perihal|kepada|yth)\b/i', $line)) {
                continue;
            }

            // minimal ada angka dan karakter pemisah umum ( / - . )
            if (preg_match('/[0-9]/', $line) && preg_match('/[\/\.-]/', $line) && ! preg_match('/\s{3,}/', $line)) {
                $candidate = $this->cleanNomorCandidate($line);

                if ($candidate) {
                    return $candidate;
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

        $lines = $this->normalizeLines($text);

        if (! $lines) {
            return null;
        }

        // ========== POLA 1: "Hal : Revisi Undangan ..." / "Perihal: Undangan ..." (1 baris) ==========
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-–—]?\s*(.+)$/iu', $line, $matches)) {
                $subject = $this->cleanSubject($matches[2] ?? '');

                if ($subject !== null) {
                    return $subject;
                }
            }
        }

        // ========== POLA 2: "Hal" / "Perihal" sendirian, isi baris setelahnya (bisa multi-line) ==========
        for ($i = 0; $i < count($lines); $i++) {
            $current = $lines[$i];

            if ($current === '') {
                continue;
            }

            if (preg_match('/^\s*(hal|perihal)\b\s*[:\-–—]?\s*$/iu', $current)) {
                $subject = $this->collectSubjectAfter($lines, $i + 1);

                if ($subject !== null) {
                    return $subject;
                }
            }
        }

        return null;
    }

    /**
     * Normalisasi teks PDF menjadi array baris yang rapi.
     */
    private function normalizeLines(string $text): array
    {
        $cleanText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text) ?? '';
        $cleanText = preg_replace('/[ \t]+/', ' ', $cleanText) ?? '';

        $rawLines = preg_split("/\r\n|\n|\r/", $cleanText) ?: [];

        return array_map(static fn (string $line) => trim($line), $rawLines);
    }

    /**
     * Bersihkan calon nomor surat dan validasi bentuknya.
     */
    private function cleanNomorCandidate(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        // Hapus potongan kata lain yang mungkin nempel di belakang
        $candidate = preg_split('/\b(sifat|lampiran|hal|perihal|kepada)\b/i', $candidate)[0] ?? $candidate;
        $candidate = preg_split('/\s{2,}/', $candidate)[0] ?? $candidate;
        $candidate = trim($candidate, " :-–—\t");

        // Nomor surat umumnya tidak panjang dan tidak mengandung kalimat utuh
        if (strlen($candidate) < 3 || strlen($candidate) > 120) {
            return null;
        }

        if (! preg_match('/[0-9]/', $candidate)) {
            return null;
        }

        // Pastikan ada pemisah umum nomor surat (/, -, .)
        if (! preg_match('/[\/\.-]/', $candidate)) {
            return null;
        }

        // Tolak jika terlalu banyak spasi (indikasi kalimat)
        if (preg_match('/\s{3,}/', $candidate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Ringkas teks subject dan batasi panjang.
     */
    private function cleanSubject(string $subject): ?string
    {
        $subject = trim($subject, " :-–—\t");

        if ($subject === '') {
            return null;
        }

        $subject = preg_split('/\b(nomor|no\.?|sifat|lampiran)\b/i', $subject)[0] ?? $subject;
        $subject = preg_replace('/^[-•*]/u', '', $subject) ?? $subject;
        $subject = preg_replace('/\s+/', ' ', $subject) ?? $subject;
        $subject = trim($subject);

        if ($subject === '') {
            return null;
        }

        return mb_strimwidth($subject, 0, 200, '...');
    }

    /**
     * Ambil perihal yang berada di beberapa baris setelah label "Hal/Perihal".
     */
    private function collectSubjectAfter(array $lines, int $startIndex): ?string
    {
        $chunks = [];

        for ($j = $startIndex; $j < min($startIndex + 5, count($lines)); $j++) {
            $candidate = $lines[$j];

            if ($candidate === '' || $candidate === ':') {
                continue;
            }

            if (preg_match('/^(nomor|no\.?|sifat|lampiran)\b/i', $candidate)) {
                break;
            }

            $chunks[] = $candidate;

            // Jika kalimat sudah cukup panjang, tidak perlu lanjut baris berikutnya
            if (mb_strlen(implode(' ', $chunks)) > 80) {
                break;
            }
        }

        if ($chunks === []) {
            return null;
        }

        return $this->cleanSubject(implode(' ', $chunks));
    }
}
