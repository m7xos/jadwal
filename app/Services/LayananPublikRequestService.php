<?php

namespace App\Services;

use App\Models\LayananPublikRequest;
use App\Models\LayananPublikStatusLog;
use App\Support\PhoneNumber;
use App\Support\RoleAccess;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LayananPublikRequestService
{
    /**
     * @param array<string, mixed> $data
     */
    public function createRequest(array $data, ?int $personilId = null): LayananPublikRequest
    {
        $status = $data['status'] ?? LayananPublikRequest::STATUS_REGISTERED;
        $tanggalSelesai = $data['tanggal_selesai'] ?? null;
        $tanggalMasuk = $data['tanggal_masuk'] ?? now();

        if (! $tanggalSelesai && in_array($status, [
            LayananPublikRequest::STATUS_COMPLETED,
            LayananPublikRequest::STATUS_PICKED_BY_VILLAGE,
        ], true)) {
            $tanggalSelesai = now();
        }

        $request = DB::transaction(function () use ($data, $status, $tanggalSelesai, $tanggalMasuk) {
            $kodeRegister = $this->generateRegisterCode();
            $queueNumber = $this->nextQueueNumber($tanggalMasuk);

            return LayananPublikRequest::create([
                'layanan_publik_id' => $data['layanan_publik_id'],
                'kode_register' => $kodeRegister,
                'queue_number' => $queueNumber,
                'nama_pemohon' => $data['nama_pemohon'],
                'no_wa_pemohon' => $data['no_wa_pemohon'] ?? null,
                'status' => $status,
                'tanggal_masuk' => $tanggalMasuk,
                'tanggal_selesai' => $tanggalSelesai,
                'perangkat_desa_nama' => $data['perangkat_desa_nama'] ?? null,
                'perangkat_desa_wa' => $data['perangkat_desa_wa'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'source' => $data['source'] ?? 'manual',
            ]);
        });

        $catatan = $data['catatan_progres'] ?? null;
        $this->logStatus($request, $status, $catatan, $personilId);
        $this->notifyStatusChange($request);
        $this->notifyNewRequest($request);

        return $request;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRequest(LayananPublikRequest $request, array $data, ?int $personilId = null): LayananPublikRequest
    {
        $note = $data['catatan_progres'] ?? null;
        unset($data['catatan_progres']);

        $originalStatus = $request->status;
        $request->fill($data);

        if ($this->shouldSetCompletedDate($request) && empty($request->tanggal_selesai)) {
            $request->tanggal_selesai = now();
        }

        $request->save();

        $note = is_string($note) ? trim($note) : $note;

        if ($request->status !== $originalStatus || ($note !== null && $note !== '')) {
            $this->logStatus($request, $request->status, $note, $personilId);

            if ($request->status !== $originalStatus) {
                $this->notifyStatusChange($request);
            }
        }

        return $request;
    }

    protected function logStatus(LayananPublikRequest $request, string $status, ?string $note, ?int $personilId): void
    {
        LayananPublikStatusLog::create([
            'layanan_publik_request_id' => $request->id,
            'status' => $status,
            'catatan' => $note,
            'created_by_personil_id' => $personilId,
        ]);
    }

    protected function notifyStatusChange(LayananPublikRequest $request): void
    {
        if (! in_array($request->status, [
            LayananPublikRequest::STATUS_COMPLETED,
            LayananPublikRequest::STATUS_PICKED_BY_VILLAGE,
        ], true)) {
            return;
        }

        $recipient = PhoneNumber::normalize($request->no_wa_pemohon);
        if (! $recipient) {
            return;
        }

        $layanan = $request->layanan?->nama ?? 'Layanan Publik';
        $statusLabel = $request->status_label;
        $url = url('/layanan/status/' . $request->kode_register);

        $message = "*Informasi Layanan Publik*\n"
            . "Kode: *{$request->kode_register}*\n"
            . "Layanan: *{$layanan}*\n"
            . "Status: *{$statusLabel}*\n";

        if ($request->status === LayananPublikRequest::STATUS_PICKED_BY_VILLAGE) {
            $nama = $request->perangkat_desa_nama ?: '-';
            $wa = $request->perangkat_desa_wa ?: '-';
            $message .= "Diambil oleh: {$nama} ({$wa})\n";
        }

        $message .= "Cek status: {$url}";

        try {
            app(WaGatewayService::class)->sendPersonalText([$recipient], $message);
        } catch (\Throwable $e) {
            Log::error('Gagal kirim notifikasi layanan publik', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shouldSetCompletedDate(LayananPublikRequest $request): bool
    {
        return in_array($request->status, [
            LayananPublikRequest::STATUS_COMPLETED,
            LayananPublikRequest::STATUS_PICKED_BY_VILLAGE,
        ], true);
    }

    protected function generateRegisterCode(): string
    {
        $prefix = 'LP-' . now()->format('ymd') . '-';

        do {
            $candidate = $prefix . strtoupper(Str::random(4));
        } while (LayananPublikRequest::query()->where('kode_register', $candidate)->exists());

        return $candidate;
    }

    protected function nextQueueNumber($tanggalMasuk): int
    {
        $tanggal = $tanggalMasuk instanceof \DateTimeInterface ? $tanggalMasuk : now();

        $last = LayananPublikRequest::query()
            ->whereDate('tanggal_masuk', $tanggal)
            ->orderByDesc('queue_number')
            ->lockForUpdate()
            ->value('queue_number');

        return ((int) $last) + 1;
    }

    protected function notifyNewRequest(LayananPublikRequest $request): void
    {
        if (! $request->relationLoaded('layanan')) {
            $request->load('layanan');
        }

        $layanan = $request->layanan?->nama ?? 'Layanan Publik';
        $title = 'Permohonan layanan baru';
        $body = 'Kode: ' . $request->kode_register
            . ' · Antrian: ' . ($request->queue_number ?? '-')
            . ' · Layanan: ' . $layanan
            . ' · Pemohon: ' . $request->nama_pemohon;

        $personils = \App\Models\Personil::query()
            ->whereNotNull('role')
            ->get();

        foreach ($personils as $personil) {
            if (! RoleAccess::canSeeNav($personil, 'filament.admin.resources.layanan-publik-register')) {
                continue;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->sendToDatabase($personil);
        }
    }
}
