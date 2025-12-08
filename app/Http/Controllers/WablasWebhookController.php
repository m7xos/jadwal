<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Personil;
use App\Models\TindakLanjutReminderLog;
use App\Services\WablasService;
use App\Services\VehicleTaxPaymentService;
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

        // ==== Handle laporan pajak terbayar ====
        if ($this->handleVehicleTaxPaid($payload, $wablas)) {
            return response()->json(['status' => 'ok']);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return response()->json(['ignored' => 'empty message']);
        }

        $normalizedMessage = strtolower(preg_replace('/\s+/', ' ', $message));

        $explicitId = $this->extractKegiatanIdFromMessage($message);
        $containsSelesai = str_contains($normalizedMessage, 'selesai');

        if (! $containsSelesai && ! in_array($normalizedMessage, ['selesai tl', 'tl selesai'])) {
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

        $kegiatan = $this->resolveKegiatanForCompletion($explicitId);
        if (! $kegiatan) {
            return response()->json(['ignored' => 'no pending TL found']);
        }

        [$isAuthorized, $allowedNumbers] = $this->isAuthorizedSender($kegiatan, $senderDigits, $payload);

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
            '*TERIMA KASIH*\nSurat sudah ditindaklanjuti.\nKode Pengingat: *TL-' . $kegiatan->id . "*\nNomor: *" . ($kegiatan->nomor ?? '-') . "*\nPerihal: *" . ($kegiatan->nama_kegiatan ?? '-') . '*'
        );

        if (! $sent) {
            Log::error('Wablas webhook: gagal kirim balasan terima kasih', [
                'kegiatan_id' => $kegiatan->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleVehicleTaxPaid(array $payload, WablasService $wablas): bool
    {
        $text = trim((string) ($payload['message'] ?? ''));

        if ($text === '') {
            return false;
        }

        if (! preg_match('/pajak-([a-z0-9 ]+)\s+terbayar/i', $text, $matches)) {
            return false;
        }

        $plat = strtoupper(str_replace(' ', '', $matches[1] ?? ''));

        /** @var VehicleTaxPaymentService $service */
        $service = app(VehicleTaxPaymentService::class);
        $vehicle = $service->markPaidByPlat($plat);

        if (! $vehicle) {
            return false;
        }

        $senderRaw = (string) ($payload['sender'] ?? '');
        $senderDigits = preg_replace('/[^0-9]/', '', $senderRaw) ?? '';
        $sender = $this->normalizeNumberFromDb($senderDigits) ?? $senderDigits;

        $thanks = "*Terima kasih.*\nPembayaran pajak kendaraan {$vehicle->plat_nomor} tercatat *LUNAS*.\n";

        $wablas->sendPersonalText([$sender], $thanks);

        return true;
    }

    protected function resolveKegiatanForCompletion(?int $kegiatanId = null): ?Kegiatan
    {
        if ($kegiatanId) {
            return Kegiatan::query()
                ->where('id', $kegiatanId)
                ->where('jenis_surat', 'tindak_lanjut')
                ->whereNull('tindak_lanjut_selesai_at')
                ->first();
        }

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
    protected function isAuthorizedSender(Kegiatan $kegiatan, string $senderDigits, array $payload = []): array
    {
        $kegiatan->loadMissing('personils');

        $allowedJabatan = $this->allowedRoles();
        $rolePatterns = $this->allowedRolePatterns();

        $assignedNumbers = ($kegiatan->personils ?? collect())
            ->map(fn (Personil $personil) => $this->normalizeNumberFromDb($personil->no_wa))
            ->filter()
            ->values();

        // Pastikan nomor personil terhubung tetap masuk meski relasi belum dimuat lengkap.
        $assignedFromDb = $kegiatan->personils()
            ->pluck('personils.no_wa')
            ->map(fn ($noWa) => $this->normalizeNumberFromDb($noWa))
            ->filter()
            ->values();

        $roleNumbers = Personil::query()
            ->where(function ($q) use ($allowedJabatan, $rolePatterns) {
                $q->whereIn('jabatan', $allowedJabatan);

                foreach ($rolePatterns as $pattern) {
                    $q->orWhere('jabatan', 'like', $pattern);
                }
            })
            ->get()
            ->map(fn (Personil $personil) => $this->normalizeNumberFromDb($personil->no_wa))
            ->filter()
            ->values();

        $allowedNumbers = $assignedNumbers
            ->merge($assignedFromDb)
            ->merge($roleNumbers)
            ->merge($this->allowedNumbersFromPayload($payload))
            ->merge($this->allowedNumbersFromConfig())
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

    protected function allowedRolePatterns(): array
    {
        return [
            '%arsiparis%',
            '%pranata komputer%',
        ];
    }

    protected function allowedNumbersFromPayload(array $payload): array
    {
        $numbers = collect();

        $groupSender = data_get($payload, 'group.sender');
        $groupOwner = data_get($payload, 'group.owner');

        foreach ([$groupSender, $groupOwner] as $raw) {
            $normalized = $this->normalizeNumberFromDb($raw);
            if ($normalized) {
                $numbers->push($normalized);
            }
        }

        return $numbers->unique()->values()->all();
    }

    protected function allowedNumbersFromConfig(): array
    {
        $raw = (string) config('wablas.finish_whitelist', '');

        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($item) => $this->normalizeNumberFromDb(trim($item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function extractKegiatanIdFromMessage(string $message): ?int
    {
        $matches = [];
        // Terima berbagai format penulisan, misalnya "TL-123", "TL - 123", atau "TL: 123".
        if (preg_match('/tl[^0-9]*(\d+)/i', $message, $matches)) {
            return (int) $matches[1];
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
