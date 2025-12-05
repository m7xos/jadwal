<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Personil;
use App\Models\TindakLanjutReminderLog;
use App\Services\WablasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WablasWebhookController extends Controller
{
    public function __invoke(Request $request, WablasService $wablas): JsonResponse
    {
        $payload = $request->all();

        Log::info('Wablas webhook received', ['payload' => $payload]);

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return response()->json(['ignored' => 'empty message']);
        }

        $normalizedMessage = strtolower(preg_replace('/\s+/', ' ', $message));
        if ($normalizedMessage !== 'selesai tl') {
            return response()->json(['ignored' => 'not a selesai tl command']);
        }

        if (! ($payload['isGroup'] ?? false)) {
            return response()->json(['ignored' => 'not a group message']);
        }

        $senderRaw = (string) ($payload['sender'] ?? '');
        $senderDigits = preg_replace('/[^0-9]/', '', $senderRaw) ?? '';
        if ($senderDigits === '') {
            return response()->json(['ignored' => 'no sender number']);
        }

        $kegiatan = $this->resolveKegiatanForCompletion();
        if (! $kegiatan) {
            return response()->json(['ignored' => 'no pending TL found']);
        }

        [$isAuthorized, $allowedNumbers] = $this->isAuthorizedSender($kegiatan, $senderDigits);

        if (! $isAuthorized) {
            Log::warning('Unauthorized selesai tl command', [
                'kegiatan_id' => $kegiatan->id,
                'sender' => $senderDigits,
                'allowed_numbers' => $allowedNumbers,
            ]);

            return response()->json(['ignored' => 'sender not authorized']);
        }

        $kegiatan->forceFill([
            'tindak_lanjut_selesai_at' => Carbon::now(),
        ])->save();

        $this->markReminderLogCompleted($kegiatan);

        $sent = $wablas->sendGroupText(
            '*TERIMA KASIH*\nSurat sudah ditindaklanjuti.\nJudul Pengingat: *TL-' . $kegiatan->id . "*\nNomor: *" . ($kegiatan->nomor ?? '-') . "*\nPerihal: *" . ($kegiatan->nama_kegiatan ?? '-') . '*'
        );

        if (! $sent) {
            Log::error('Wablas webhook: gagal kirim balasan terima kasih', [
                'kegiatan_id' => $kegiatan->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function resolveKegiatanForCompletion(): ?Kegiatan
    {
        $log = TindakLanjutReminderLog::query()
            ->whereHas('kegiatan', fn ($q) => $q
                ->where('jenis_surat', 'tindak_lanjut')
                ->whereNull('tindak_lanjut_selesai_at'))
            ->orderByDesc('created_at')
            ->first();

        return $log?->kegiatan;
    }

    /**
     * @return array{0: bool, 1: array<int, string>} [authorized, allowed_numbers]
     */
    protected function isAuthorizedSender(Kegiatan $kegiatan, string $senderDigits): array
    {
        $kegiatan->loadMissing('personils');

        $allowedJabatan = $this->allowedRoles();

        $assignedNumbers = ($kegiatan->personils ?? collect())
            ->map(fn (Personil $personil) => $this->normalizeNumberFromDb($personil->no_wa))
            ->filter()
            ->values();

        $roleNumbers = Personil::query()
            ->whereIn('jabatan', $allowedJabatan)
            ->get()
            ->map(fn (Personil $personil) => $this->normalizeNumberFromDb($personil->no_wa))
            ->filter()
            ->values();

        $allowedNumbers = $assignedNumbers
            ->merge($roleNumbers)
            ->unique()
            ->values()
            ->all();

        $senderNormalized = $this->normalizeNumber($senderDigits);
        $authorized = in_array($senderNormalized, $allowedNumbers, true);

        return [$authorized, $allowedNumbers];
    }

    protected function allowedRoles(): array
    {
        return [
            'Arsiparis Terampil',
            'Pranata Komputer Terampil',
        ];
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

    protected function normalizeNumberFromDb(?string $raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) ($raw ?? '')) ?? '';

        if ($digits === '') {
            return null;
        }

        return $this->normalizeNumber($digits);
    }

    protected function markReminderLogCompleted(Kegiatan $kegiatan): void
    {
        $log = $kegiatan->tindakLanjutReminderLogs()
            ->latest()
            ->first();

        if (! $log) {
            return;
        }

        $log->status = 'success';
        $log->error_message = null;
        $log->response = ['completed_via' => 'webhook'];
        $log->sent_at = $log->sent_at ?? Carbon::now();
        $log->save();
    }
}
