<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PdfCompressor
{
    /** @var string[] */
    private array $qualityPresets = ['/ebook', '/screen'];

    /**
     * Compress a PDF file in place using Ghostscript.
     *
     * @param  string  $path        Absolute path or path relative to the public disk.
     * @param  int|null  $targetBytes  Optional max size target; compression stops once under this.
     */
    public function compress(string $path, ?int $targetBytes = null): ?string
    {
        $fullPath = $this->resolvePath($path);

        if (! $fullPath || ! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $binary = $this->findGhostscriptBinary();

        if (! $binary) {
            return null;
        }

        $originalSize = filesize($fullPath) ?: 0;

        if ($targetBytes !== null && $originalSize > 0 && $originalSize <= $targetBytes) {
            return $fullPath;
        }

        $bestOutput = null;
        $bestSize = $originalSize > 0 ? $originalSize : PHP_INT_MAX;

        foreach ($this->qualityPresets as $preset) {
            $outputPath = $this->makeTempOutputPath($preset);

            if (! $this->optimizeWithGhostscript($binary, $preset, $fullPath, $outputPath)) {
                $this->deleteIfExists($outputPath);
                continue;
            }

            $optimizedSize = filesize($outputPath) ?: null;

            if ($optimizedSize !== null && $optimizedSize < $bestSize) {
                $this->deleteIfExists($bestOutput);
                $bestOutput = $outputPath;
                $bestSize = $optimizedSize;
            } else {
                $this->deleteIfExists($outputPath);
            }

            if ($targetBytes !== null && $optimizedSize !== null && $optimizedSize <= $targetBytes) {
                break;
            }
        }

        if ($bestOutput && is_file($bestOutput) && $bestSize < $originalSize) {
            $this->replaceFile($bestOutput, $fullPath);

            return $fullPath;
        }

        $this->deleteIfExists($bestOutput);

        return $fullPath;
    }

    private function resolvePath(string $path): ?string
    {
        if (is_file($path)) {
            return $path;
        }

        $diskPath = Storage::disk('public')->path($path);

        return is_file($diskPath) ? $diskPath : null;
    }

    private function findGhostscriptBinary(): ?string
    {
        $candidates = ['gs', 'gswin64c', 'gswin32c'];

        foreach ($candidates as $binary) {
            $located = $this->locateBinary($binary);

            if ($located) {
                return $located;
            }
        }

        return null;
    }

    private function locateBinary(string $binary): ?string
    {
        $command = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? ['where', $binary]
            : ['which', $binary];

        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return null;
        }

        $firstLine = preg_split("/\r\n|\n|\r/", $output)[0] ?? '';

        return $firstLine !== '' ? $firstLine : null;
    }

    private function makeTempOutputPath(string $preset): string
    {
        $sluggedPreset = str_replace('/', '', $preset);

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'pdfcompress_' . uniqid($sluggedPreset . '_', true) . '.pdf';
    }

    private function optimizeWithGhostscript(string $binary, string $preset, string $inputPath, string $outputPath): bool
    {
        $process = new Process([
            $binary,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            "-dPDFSETTINGS={$preset}",
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            "-sOutputFile={$outputPath}",
            $inputPath,
        ]);

        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful() && is_file($outputPath);
    }

    private function replaceFile(string $source, string $destination): void
    {
        $tempReplacement = $destination . '.tmp';

        if (copy($source, $tempReplacement)) {
            rename($tempReplacement, $destination);
        }

        $this->deleteIfExists($source);
    }

    private function deleteIfExists(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}
