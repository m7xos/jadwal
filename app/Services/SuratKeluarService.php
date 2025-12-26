<?php

namespace App\Services;

use App\Models\KodeSurat;
use App\Models\SuratKeluar;
use App\Models\SuratKeluarCounter;
use Illuminate\Support\Facades\DB;

class SuratKeluarService
{
    /**
     * @param array<string, mixed> $context
     */
    public function createMaster(KodeSurat $kodeSurat, string $perihal, array $context = []): SuratKeluar
    {
        $tahun = (int) ($context['tahun'] ?? now()->year);
        $tanggalSurat = $context['tanggal_surat'] ?? now();

        return DB::transaction(function () use ($kodeSurat, $perihal, $context, $tahun, $tanggalSurat) {
            $counter = SuratKeluarCounter::query()
                ->where('kode_surat_id', $kodeSurat->id)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = SuratKeluarCounter::create([
                    'kode_surat_id' => $kodeSurat->id,
                    'tahun' => $tahun,
                    'last_number' => 0,
                ]);
            }

            $base = (int) ($counter->last_number ?? 0);
            $nextNumber = $this->nextAvailableMasterNumber($kodeSurat->id, $tahun, $base + 1);

            $counter->last_number = $nextNumber;
            $counter->save();

            return SuratKeluar::create([
                'kode_surat_id' => $kodeSurat->id,
                'tahun' => $tahun,
                'nomor_urut' => $nextNumber,
                'nomor_sisipan' => 0,
                'tanggal_surat' => $tanggalSurat,
                'master_id' => null,
                'perihal' => $perihal,
                'status' => SuratKeluar::STATUS_ISSUED,
                'booked_at' => null,
                'requested_by_number' => $context['requested_by_number'] ?? null,
                'requested_by_personil_id' => $context['requested_by_personil_id'] ?? null,
                'request_id' => $context['request_id'] ?? null,
                'source' => $context['source'] ?? 'wa',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function createSisipan(SuratKeluar $master, string $perihal, array $context = []): SuratKeluar
    {
        $tahun = $master->tahun;
        $kodeId = $master->kode_surat_id;
        $nomorUrut = $master->nomor_urut;
        $tanggalSurat = $context['tanggal_surat'] ?? $master->tanggal_surat ?? now();

        return DB::transaction(function () use ($master, $perihal, $context, $tahun, $kodeId, $nomorUrut, $tanggalSurat) {
            $maxSisipan = SuratKeluar::query()
                ->where('kode_surat_id', $kodeId)
                ->where('tahun', $tahun)
                ->where('nomor_urut', $nomorUrut)
                ->lockForUpdate()
                ->max('nomor_sisipan');

            $next = ((int) $maxSisipan) + 1;

            return SuratKeluar::create([
                'kode_surat_id' => $kodeId,
                'tahun' => $tahun,
                'nomor_urut' => $nomorUrut,
                'nomor_sisipan' => $next,
                'tanggal_surat' => $tanggalSurat,
                'master_id' => $master->id,
                'perihal' => $perihal,
                'status' => SuratKeluar::STATUS_ISSUED,
                'booked_at' => null,
                'requested_by_number' => $context['requested_by_number'] ?? null,
                'requested_by_personil_id' => $context['requested_by_personil_id'] ?? null,
                'request_id' => $context['request_id'] ?? null,
                'source' => $context['source'] ?? 'manual',
            ]);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function createBooking(KodeSurat $kodeSurat, int $tahun, int $nomor, array $context = []): SuratKeluar
    {
        $tanggalBooking = $context['booked_at'] ?? now();

        return DB::transaction(function () use ($kodeSurat, $tahun, $nomor, $context, $tanggalBooking) {
            $exists = SuratKeluar::query()
                ->where('kode_surat_id', $kodeSurat->id)
                ->where('tahun', $tahun)
                ->where('nomor_urut', $nomor)
                ->where('nomor_sisipan', 0)
                ->exists();

            if ($exists) {
                throw new \RuntimeException('Nomor surat sudah terpakai.');
            }

            return SuratKeluar::create([
                'kode_surat_id' => $kodeSurat->id,
                'tahun' => $tahun,
                'nomor_urut' => $nomor,
                'nomor_sisipan' => 0,
                'tanggal_surat' => null,
                'master_id' => null,
                'perihal' => SuratKeluar::BOOKED_PLACEHOLDER,
                'status' => SuratKeluar::STATUS_BOOKED,
                'booked_at' => $tanggalBooking,
                'requested_by_number' => $context['requested_by_number'] ?? null,
                'requested_by_personil_id' => $context['requested_by_personil_id'] ?? null,
                'request_id' => $context['request_id'] ?? null,
                'source' => $context['source'] ?? 'manual',
            ]);
        });
    }

    public function previewNextMasterNumber(KodeSurat $kodeSurat, int $tahun): int
    {
        $counter = SuratKeluarCounter::query()
            ->where('kode_surat_id', $kodeSurat->id)
            ->where('tahun', $tahun)
            ->first();

        $base = (int) ($counter?->last_number ?? 0);

        return $this->nextAvailableMasterNumber($kodeSurat->id, $tahun, $base + 1);
    }

    public function previewNextSisipanNumber(SuratKeluar $master): int
    {
        $maxSisipan = SuratKeluar::query()
            ->where('kode_surat_id', $master->kode_surat_id)
            ->where('tahun', $master->tahun)
            ->where('nomor_urut', $master->nomor_urut)
            ->max('nomor_sisipan');

        return ((int) $maxSisipan) + 1;
    }

    public function previewNextMasterNumberText(?int $kodeSuratId, ?int $masterId): string
    {
        if ($masterId) {
            $master = SuratKeluar::find($masterId);
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

    /**
     * @return array{available: array<int, int>, booked: array<int, array{nomor: int, booked_at: string|null}>}
     */
    public function getNumberingStatus(KodeSurat $kodeSurat, int $tahun): array
    {
        $masters = SuratKeluar::query()
            ->where('kode_surat_id', $kodeSurat->id)
            ->where('tahun', $tahun)
            ->where('nomor_sisipan', 0)
            ->get(['nomor_urut', 'status', 'booked_at']);

        $used = $masters->pluck('nomor_urut')->map(fn ($value) => (int) $value)->unique()->values();

        $maxExisting = $used->max() ?? 0;
        $counter = SuratKeluarCounter::query()
            ->where('kode_surat_id', $kodeSurat->id)
            ->where('tahun', $tahun)
            ->first();

        $maxNumber = max((int) ($counter?->last_number ?? 0), (int) $maxExisting);

        $available = [];
        if ($maxNumber > 0) {
            for ($i = 1; $i <= $maxNumber; $i++) {
                if (! $used->contains($i)) {
                    $available[] = $i;
                }
            }
        }

        $booked = $masters
            ->where('status', SuratKeluar::STATUS_BOOKED)
            ->sortBy('nomor_urut')
            ->map(fn (SuratKeluar $surat) => [
                'nomor' => (int) $surat->nomor_urut,
                'booked_at' => $surat->booked_at?->format('d/m/Y'),
            ])
            ->values()
            ->all();

        return [
            'available' => $available,
            'booked' => $booked,
        ];
    }

    protected function nextAvailableMasterNumber(int $kodeSuratId, int $tahun, int $start): int
    {
        $candidate = $start;

        while (SuratKeluar::query()
            ->where('kode_surat_id', $kodeSuratId)
            ->where('tahun', $tahun)
            ->where('nomor_urut', $candidate)
            ->where('nomor_sisipan', 0)
            ->exists()) {
            $candidate++;
        }

        return $candidate;
    }
}
