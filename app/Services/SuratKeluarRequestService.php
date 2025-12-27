<?php

namespace App\Services;

use App\Models\KodeSurat;
use App\Models\Personil;
use App\Models\SuratKeluarRequest;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

class SuratKeluarRequestService
{
    public function handleIncoming(array $payload, WaGatewayService $waGateway): bool
    {
        $message = $this->extractText($payload);
        if ($message === null) {
            return false;
        }

        $sender = $this->extractSenderNumber($payload);
        $normalizedMessage = strtolower(preg_replace('/\s+/', ' ', $message));
        $groupId = $this->extractGroupId($payload);

        if ($this->isStartCommand($normalizedMessage)) {
            if (! $sender) {
                return false;
            }

            $this->startRequest($sender, $groupId, $waGateway);
            return true;
        }

        $request = $sender ? SuratKeluarRequest::activeFor($sender)->first() : null;
        if (! $request && ! $sender) {
            $request = $this->resolveFallbackRequest($message);
        }
        if (! $request) {
            return false;
        }

        if ($request->status === SuratKeluarRequest::STATUS_WAITING_KLASIFIKASI) {
            $this->handleKlasifikasi($request, $message, $waGateway);
            return true;
        }

        if ($request->status === SuratKeluarRequest::STATUS_WAITING_HAL) {
            $this->handlePerihal($request, $message, $waGateway);
            return true;
        }

        return false;
    }

    protected function startRequest(string $sender, ?string $groupId, WaGatewayService $waGateway): void
    {
        $existing = SuratKeluarRequest::activeFor($sender)->first();

        if ($existing) {
            $message = $existing->status === SuratKeluarRequest::STATUS_WAITING_HAL
                ? 'Permintaan nomor surat masih aktif. Silakan kirim Hal Surat untuk melanjutkan.'
                : 'Permintaan nomor surat masih aktif. Silakan kirim kode klasifikasi surat.';

            $waGateway->sendPersonalText([$sender], $message);
            return;
        }

        $requesterPersonilId = $this->resolvePersonilId($sender);

        SuratKeluarRequest::create([
            'requester_number' => $sender,
            'requester_personil_id' => $requesterPersonilId,
            'group_id' => $groupId,
            'status' => SuratKeluarRequest::STATUS_WAITING_KLASIFIKASI,
            'source' => 'wa',
        ]);

        $waGateway->sendPersonalText([$sender], 'Mohon ketik kode klasifikasi Surat');
    }

    protected function handleKlasifikasi(SuratKeluarRequest $request, string $message, WaGatewayService $waGateway): void
    {
        $kode = $this->extractKodeKlasifikasi($message);

        if (! $kode) {
            $waGateway->sendPersonalText([$request->requester_number], 'Kode klasifikasi tidak dikenali. Silakan ketik ulang.');
            return;
        }

        $kodeSurat = KodeSurat::query()->where('kode', $kode)->first();

        if (! $kodeSurat) {
            $waGateway->sendPersonalText([$request->requester_number], 'Kode klasifikasi tidak ditemukan. Silakan ketik ulang sesuai daftar kode.');
            return;
        }

        $request->kode_surat_id = $kodeSurat->id;
        $request->status = SuratKeluarRequest::STATUS_WAITING_HAL;
        $request->save();

        $waGateway->sendPersonalText([$request->requester_number], 'Masukkan Hal Surat');
    }

    protected function handlePerihal(SuratKeluarRequest $request, string $message, WaGatewayService $waGateway): void
    {
        $perihal = trim($message);

        if ($perihal === '') {
            $waGateway->sendPersonalText([$request->requester_number], 'Hal Surat tidak boleh kosong. Silakan ketik ulang.');
            return;
        }

        if (! $request->kode_surat_id) {
            $request->status = SuratKeluarRequest::STATUS_WAITING_KLASIFIKASI;
            $request->save();
            $waGateway->sendPersonalText([$request->requester_number], 'Kode klasifikasi belum terpilih. Silakan ketik kode klasifikasi surat.');
            return;
        }

        $kodeSurat = KodeSurat::find($request->kode_surat_id);
        if (! $kodeSurat) {
            $request->status = SuratKeluarRequest::STATUS_WAITING_KLASIFIKASI;
            $request->kode_surat_id = null;
            $request->save();
            $waGateway->sendPersonalText([$request->requester_number], 'Kode klasifikasi tidak ditemukan. Silakan ketik ulang.');
            return;
        }

        /** @var SuratKeluarService $suratService */
        $suratService = app(SuratKeluarService::class);

        try {
            $surat = $suratService->createMaster($kodeSurat, $perihal, [
                'requested_by_number' => $request->requester_number,
                'requested_by_personil_id' => $request->requester_personil_id,
                'request_id' => $request->id,
                'source' => 'wa',
                'tanggal_surat' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal membuat nomor surat keluar dari WA', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            $waGateway->sendPersonalText([$request->requester_number], 'Mohon maaf, terjadi kesalahan saat membuat nomor surat. Silakan coba lagi.');
            return;
        }

        $request->perihal = $perihal;
        $request->status = SuratKeluarRequest::STATUS_COMPLETED;
        $request->save();

        $message = 'Terima Kasih berikut nomor surat yang anda minta, *Nomor: '
            . $surat->nomor_label . '*'
            . "\nHal: " . $perihal;

        $waGateway->sendPersonalText([$request->requester_number], $message);
    }

    protected function extractKodeKlasifikasi(string $message): ?string
    {
        if (preg_match('/([0-9]+(?:\\.[0-9]+)*)/', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function isStartCommand(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        if (str_contains($message, 'minta nomor surat keluar')) {
            return true;
        }

        return str_contains($message, 'minta nomor surat');
    }

    protected function resolveFallbackRequest(string $message): ?SuratKeluarRequest
    {
        $candidates = SuratKeluarRequest::query()
            ->whereIn('status', [
                SuratKeluarRequest::STATUS_WAITING_KLASIFIKASI,
                SuratKeluarRequest::STATUS_WAITING_HAL,
            ])
            ->orderByDesc('updated_at')
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            return null;
        }

        $request = $candidates->first();

        Log::warning('Surat keluar request fallback dipakai (sender tidak terbaca)', [
            'request_id' => $request?->id,
            'message' => $message,
        ]);

        return $request;
    }

    protected function extractText(array $payload): ?string
    {
        $message = $payload['message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        if (is_array($message)) {
            foreach (['text', 'conversation', 'caption', 'body'] as $key) {
                $value = $message[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        $candidates = [
            $payload['text'] ?? null,
            $payload['body'] ?? null,
            data_get($payload, 'message.text'),
            data_get($payload, 'message.conversation'),
            data_get($payload, 'message.caption'),
            data_get($payload, 'message.body'),
        ];

        foreach ($candidates as $text) {
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return null;
    }

    protected function extractSenderNumber(array $payload): ?string
    {
        $isGroup = (bool) ($payload['isGroup'] ?? false);
        $candidates = [
            (string) ($payload['participant'] ?? ''),
            (string) ($payload['sender'] ?? ''),
            $isGroup ? '' : (string) ($payload['from'] ?? ''),
        ];

        foreach ($candidates as $raw) {
            if ($raw === '') {
                continue;
            }

            if (str_contains($raw, '@g.us') || str_contains($raw, '@lid')) {
                continue;
            }

            $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
            if ($digits === '') {
                continue;
            }

            return $this->normalizeNumber($digits);
        }

        return null;
    }

    protected function normalizeNumber(string $digits): string
    {
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (! str_starts_with($digits, '62')) {
            return '62' . $digits;
        }

        return $digits;
    }

    protected function resolvePersonilId(string $normalizedNumber): ?int
    {
        $personils = Personil::query()
            ->whereNotNull('no_wa')
            ->where('no_wa', '!=', '')
            ->get(['id', 'no_wa']);

        foreach ($personils as $personil) {
            if (PhoneNumber::normalize($personil->no_wa) === $normalizedNumber) {
                return $personil->id;
            }
        }

        return null;
    }

    protected function extractGroupId(array $payload): ?string
    {
        $group = $payload['group'] ?? null;

        if (is_array($group)) {
            $candidates = [
                $group['id'] ?? null,
                $group['number'] ?? null,
                $group['group_id'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $from = trim((string) ($payload['from'] ?? ''));

        if ($from !== '' && str_contains($from, '@g.us')) {
            return $from;
        }

        return null;
    }
}
