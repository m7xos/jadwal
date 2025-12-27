<?php

namespace App\Services;

use App\Models\KodeSurat;
use App\Models\SuratKeputusan;
use App\Models\SuratKeputusanGlobalCounter;
use Illuminate\Support\Facades\DB;

class SuratKeputusanService
{
    /**
     * @param array<string, mixed> $context
     */
    public function createMaster(KodeSurat $kodeSurat, string $perihal, array $context = []): SuratKeputusan
    {
        $tahun = (int) ($context['tahun'] ?? now()->year);
        $tanggalSurat = $context['tanggal_surat'] ?? now();
        $tanggalDiundangkan = $context['tanggal_diundangkan'] ?? null;

        return DB::transaction(function () use ($kodeSurat, $perihal, $context, $tahun, $tanggalSurat, $tanggalDiundangkan) {
            $counter = SuratKeputusanGlobalCounter::query()
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = SuratKeputusanGlobalCounter::create([
                    'tahun' => $tahun,
                    'last_number' => 0,
                ]);
            }

            $nextNumber = $this->nextAvailableGlobalNumber($tahun);
            $counter->last_number = max((int) ($counter->last_number ?? 0), $nextNumber);
            $counter->save();

            return SuratKeputusan::create([
                'kode_surat_id' => $kodeSurat->id,
                'tahun' => $tahun,
                'nomor_urut' => $nextNumber,
                'nomor_sisipan' => 0,
                'tanggal_surat' => $tanggalSurat,
                'tanggal_diundangkan' => $tanggalDiundangkan,
                'master_id' => null,
                'perihal' => $perihal,
                'status' => SuratKeputusan::STATUS_ISSUED,
                'booked_at' => null,
                'source' => $context['source'] ?? 'manual',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function createSisipan(SuratKeputusan $master, string $perihal, array $context = []): SuratKeputusan
    {
        $tahun = $master->tahun;
        $kodeId = $master->kode_surat_id;
        $nomorUrut = $master->nomor_urut;
        $tanggalSurat = $context['tanggal_surat'] ?? $master->tanggal_surat ?? now();
        $tanggalDiundangkan = $context['tanggal_diundangkan'] ?? $master->tanggal_diundangkan;

        return DB::transaction(function () use ($master, $perihal, $context, $tahun, $kodeId, $nomorUrut, $tanggalSurat, $tanggalDiundangkan) {
            $maxSisipan = SuratKeputusan::query()
                ->where('kode_surat_id', $kodeId)
                ->where('tahun', $tahun)
                ->where('nomor_urut', $nomorUrut)
                ->lockForUpdate()
                ->max('nomor_sisipan');

            $next = ((int) $maxSisipan) + 1;

            return SuratKeputusan::create([
                'kode_surat_id' => $kodeId,
                'tahun' => $tahun,
                'nomor_urut' => $nomorUrut,
                'nomor_sisipan' => $next,
                'tanggal_surat' => $tanggalSurat,
                'tanggal_diundangkan' => $tanggalDiundangkan,
                'master_id' => $master->id,
                'perihal' => $perihal,
                'status' => SuratKeputusan::STATUS_ISSUED,
                'booked_at' => null,
                'source' => $context['source'] ?? 'manual',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function createBooking(KodeSurat $kodeSurat, int $tahun, int $nomor, array $context = []): SuratKeputusan
    {
        $tanggalBooking = $context['booked_at'] ?? now();

        return DB::transaction(function () use ($kodeSurat, $tahun, $nomor, $context, $tanggalBooking) {
            $exists = SuratKeputusan::query()
                ->where('tahun', $tahun)
                ->where('nomor_urut', $nomor)
                ->where('nomor_sisipan', 0)
                ->exists();

            if ($exists) {
                throw new \RuntimeException('Nomor surat sudah terpakai.');
            }

            return SuratKeputusan::create([
                'kode_surat_id' => $kodeSurat->id,
                'tahun' => $tahun,
                'nomor_urut' => $nomor,
                'nomor_sisipan' => 0,
                'tanggal_surat' => null,
                'tanggal_diundangkan' => null,
                'master_id' => null,
                'perihal' => SuratKeputusan::BOOKED_PLACEHOLDER,
                'status' => SuratKeputusan::STATUS_BOOKED,
                'booked_at' => $tanggalBooking,
                'source' => $context['source'] ?? 'manual',
            ]);
        });
    }

    public function previewNextMasterNumber(KodeSurat $kodeSurat, int $tahun): int
    {
        return $this->nextAvailableGlobalNumber($tahun);
    }

    public function previewNextSisipanNumber(SuratKeputusan $master): int
    {
        $maxSisipan = SuratKeputusan::query()
            ->where('kode_surat_id', $master->kode_surat_id)
            ->where('tahun', $master->tahun)
            ->where('nomor_urut', $master->nomor_urut)
            ->max('nomor_sisipan');

        return ((int) $maxSisipan) + 1;
    }

    public function previewNextMasterNumberText(?int $kodeSuratId, ?int $masterId): string
    {
        if ($masterId) {
            $master = SuratKeputusan::find($masterId);
            if (! $master) {
                return '-';
            }

            $next = $this->previewNextSisipanNumber($master);
            $label = $master->kodeSurat?->kode ?? '-';

            return $label . '/' . $master->nomor_urut . '.' . $next;
        }

        if (! $kodeSuratId) {
            return '-';
        }

        $kode = KodeSurat::find($kodeSuratId);
        if (! $kode) {
            return '-';
        }

        $next = $this->previewNextMasterNumber($kode, (int) now()->year);

        return $kode->kode . '/' . $next;
    }

    protected function nextAvailableGlobalNumber(int $tahun): int
    {
        $numbers = SuratKeputusan::query()
            ->where('tahun', $tahun)
            ->where('nomor_sisipan', 0)
            ->orderBy('nomor_urut')
            ->pluck('nomor_urut')
            ->map(fn ($value) => (int) $value);

        $expected = 1;

        foreach ($numbers as $number) {
            if ($number < $expected) {
                continue;
            }

            if ($number === $expected) {
                $expected++;
                continue;
            }

            break;
        }

        return $expected;
    }
}
