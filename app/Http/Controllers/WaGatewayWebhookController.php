<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Personil;
use App\Models\TindakLanjutReminderLog;
use App\Models\WaInboxMessage;
use App\Events\WaInboxMessageReceived;
use App\Services\FollowUpReminderService;
use App\Services\MobileNotificationService;
use App\Services\ScheduleResponder;
use App\Services\SuratKeluarRequestService;
use App\Services\WaGatewayService;
use App\Services\LayananPublikStatusResponder;
use App\Services\VehicleTaxPaymentService;
use App\Models\WaGatewaySetting;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WaGatewayWebhookController extends Controller
{
    public function __invoke(Request $request, WaGatewayService $waGateway): JsonResponse
    {
        $payload = $this->normalizeIncomingPayload($request->all());

        Log::info('WA Gateway webhook received', ['payload' => $payload]);

        // ==== Handle laporan pajak terbayar ====
        if ($this->handleVehicleTaxPaid($payload, $waGateway)) {
            return response()->json(['status' => 'ok']);
        }

        if (app(LayananPublikStatusResponder::class)->handle($payload, $waGateway)) {
            return response()->json(['status' => 'ok']);
        }

        if ($this->handleHelpCommand($payload, $waGateway)) {
            return response()->json(['status' => 'ok']);
        }

        if (app(SuratKeluarRequestService::class)->handleIncoming($payload, $waGateway)) {
            return response()->json(['status' => 'ok']);
        }

        if ($this->handleIncomingChat($payload, $waGateway)) {
            return response()->json(['status' => 'ok']);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return response()->json(['ignored' => 'empty message']);
        }

        $normalizedMessage = strtolower(preg_replace('/\s+/', ' ', $message));

        /** @var FollowUpReminderService $followUpReminder */
        $followUpReminder = app(FollowUpReminderService::class);

        // Balasan jadwal kegiatan (hari ini/besok + pending disposisi)
        if (app(ScheduleResponder::class)->handle($payload, $waGateway)) {
            return response()->json(['status' => 'ok']);
        }

        if ($followUpReminder->handleThanksReply($payload)) {
            return response()->json(['status' => 'ok']);
        }

        $explicitId = $this->extractKegiatanIdFromMessage($message);
        $containsSelesai = str_contains($normalizedMessage, 'selesai');

        if ($explicitId === null) {
            return response()->json(['ignored' => 'no tl code provided']);
        }

        if (! $containsSelesai && ! in_array($normalizedMessage, ['selesai tl', 'tl selesai'])) {
            return response()->json(['ignored' => 'not a selesai tl command']);
        }

        if (! ($payload['isGroup'] ?? false)) {
            return response()->json(['ignored' => 'not a group message']);
        }

        $participantRaw = (string) ($payload['participant'] ?? '');
        $senderRaw = (string) ($payload['sender'] ?? '');

        $senderDigits = collect([$participantRaw, $senderRaw])
            ->map(fn ($raw) => preg_replace('/[^0-9]/', '', (string) $raw) ?? '')
            ->first(fn ($digits) => $digits !== '') ?? '';

        if ($senderDigits === '') {
            return response()->json(['ignored' => 'no sender number']);
        }

        $kegiatan = $this->resolveKegiatanForCompletion($explicitId);
        if (! $kegiatan) {
            return response()->json(['ignored' => 'no pending TL found']);
        }

        $incomingGroupIdRaw = $this->extractGroupId($payload);
        $incomingGroupId = $this->normalizeGroupId($incomingGroupIdRaw);

        $targetGroupPhones = $waGateway->getTlTargetGroupPhones($kegiatan);
        $targetGroupPhonesNormalized = collect($targetGroupPhones)
            ->map(fn ($id) => $this->normalizeGroupId($id))
            ->filter()
            ->values()
            ->all();

        if (
            ! empty($targetGroupPhonesNormalized)
            && $incomingGroupId !== null
            && ! in_array($incomingGroupId, $targetGroupPhonesNormalized, true)
        ) {
            return response()->json(['ignored' => 'message from unrelated group']);
        }

        [$isAuthorized, $allowedNumbers] = $this->isAuthorizedSender($kegiatan, $senderDigits, $payload);

        if (! $isAuthorized) {
            Log::warning('Unauthorized selesai tl command', [
                'kegiatan_id' => $kegiatan->id,
                'sender' => $senderDigits,
                'allowed_numbers' => $allowedNumbers,
            ]);

            $unauthMessage = 'Mohon maaf anda bukan penerima disposisi, mohon koordinasi dengan personil terkait untuk penyelesaian TL';

            if ($incomingGroupIdRaw ?? $incomingGroupId) {
                $waGateway->sendTextToSpecificGroup($incomingGroupIdRaw ?? $incomingGroupId, $unauthMessage);
            } else {
                $waGateway->sendPersonalText(
                    [$this->normalizeNumber($senderDigits)],
                    $unauthMessage
                );
            }

            return response()->json(['ignored' => 'sender not authorized']);
        }

        $kegiatan->forceFill([
            'tindak_lanjut_selesai_at' => Carbon::now(),
        ])->save();

        $this->markReminderLogCompleted($kegiatan);

        $thanksMessage = '*TERIMA KASIH*'
            . "\nSurat sudah ditindaklanjuti."
            . "\nKode Pengingat: *TL-" . $kegiatan->id . '*'
            . "\nNomor: *" . ($kegiatan->nomor ?? '-') . '*'
            . "\nPerihal: *" . ($kegiatan->nama_kegiatan ?? '-') . '*';

        $sent = false;

        if ($incomingGroupId && in_array($incomingGroupId, $targetGroupPhonesNormalized, true)) {
            $result = $waGateway->sendTextToSpecificGroup($incomingGroupIdRaw ?? $incomingGroupId, $thanksMessage);
            $sent = (bool) ($result['success'] ?? false);
        } elseif (! empty($targetGroupPhones)) {
            foreach ($targetGroupPhones as $phone) {
                $result = $waGateway->sendTextToSpecificGroup($phone, $thanksMessage);
                if ($result['success'] ?? false) {
                    $sent = true;
                }
            }
        } else {
            $sent = $waGateway->sendGroupText($thanksMessage);
        }

        if (! $sent) {
            Log::error('WA Gateway webhook: gagal kirim balasan terima kasih', [
                'kegiatan_id' => $kegiatan->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function extractGroupId(array $payload): ?string
    {
        $group = $payload['group'] ?? null;

        if (! is_array($group)) {
            return null;
        }

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

        return null;
    }

    /**
     * Normalisasi payload webhook agar kompatibel dengan beberapa sumber.
     *
     * - Legacy format: memakai field seperti `sender`, `message`, `isGroup`, `group`.
     * - wa-gateway: umumnya memakai `from`, `message` (+ optional `sender`, `participant`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeIncomingPayload(array $payload): array
    {
        if (! array_key_exists('message', $payload) && array_key_exists('text', $payload)) {
            $payload['message'] = $payload['text'];
        }

        $from = trim((string) ($payload['from'] ?? ''));
        $participant = trim((string) ($payload['participant'] ?? ''));

        if (! array_key_exists('isGroup', $payload) && $from !== '') {
            $payload['isGroup'] = str_contains($from, '@g.us');
        }

        if (! array_key_exists('sender', $payload) || trim((string) ($payload['sender'] ?? '')) === '') {
            if ($participant !== '') {
                $payload['sender'] = $participant;
            } elseif ($from !== '' && ! (($payload['isGroup'] ?? false) === true)) {
                // Pada chat personal, `from` merepresentasikan JID pengirim.
                $payload['sender'] = $from;
            }
        }

        if (($payload['isGroup'] ?? false) && ! array_key_exists('group', $payload) && $from !== '') {
            $payload['group'] = ['id' => $from];
        }

        return $payload;
    }

    protected function handleVehicleTaxPaid(array $payload, WaGatewayService $waGateway): bool
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

        $waGateway->sendPersonalText([$sender], $thanks);

        return true;
    }

    protected function handleHelpCommand(array $payload, WaGatewayService $waGateway): bool
    {
        $text = $this->extractIncomingText($payload);
        if ($text === null) {
            return false;
        }

        if (! preg_match('/^help(\\s|$)/i', $text)) {
            return false;
        }

        $sender = $this->extractSenderNumberForHelp($payload);
        if (! $sender || ! $this->isHelpAuthorized($sender)) {
            $this->sendHelpReply($payload, $waGateway, 'Maaf kamu belum bisa menggunakan fitur ini');
            return true;
        }

        $topic = strtolower(trim(preg_replace('/^help\\s*/i', '', $text)));
        $message = $this->helpMessageFor($topic);

        return $this->sendHelpReply($payload, $waGateway, $message);
    }

    protected function handleIncomingChat(array $payload, WaGatewayService $waGateway): bool
    {
        if (($payload['isGroup'] ?? false) === true) {
            return false;
        }

        if ($this->isFromSelf($payload)) {
            return false;
        }

        $message = $this->extractIncomingText($payload);
        if ($message === null) {
            return false;
        }

        $normalized = strtolower(preg_replace('/\\s+/', ' ', $message));
        if ($this->isBotCommandMessage($normalized)) {
            return false;
        }

        $sender = $this->extractSenderNumberForChat($payload);
        if (! $sender) {
            return false;
        }

        $senderLid = null;
        if (str_contains($sender, '@lid')) {
            $senderLid = $sender;
            $resolved = $waGateway->resolveLidNumber($sender);
            if ($resolved) {
                $sender = $resolved;
            }
        }

        $exists = WaInboxMessage::query()
            ->where('sender_number', $sender)
            ->where('message', $message)
            ->where('received_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($exists) {
            return true;
        }

        $senderName = $this->extractSenderName($payload);
        if (! $senderName && ! str_contains($sender, '@lid')) {
            $senderName = $waGateway->getContactName($sender);
        }

        $meta = [
            'from' => $payload['from'] ?? null,
            'sender_raw' => $payload['sender'] ?? null,
        ];

        if ($senderLid) {
            $meta['sender_lid'] = $senderLid;
            if ($sender !== $senderLid) {
                $meta['sender_resolved'] = $sender;
            }
        }

        $inboxMessage = WaInboxMessage::create([
            'sender_number' => $sender,
            'sender_name' => $senderName,
            'message' => $message,
            'received_at' => now(),
            'status' => WaInboxMessage::STATUS_NEW,
            'meta' => $meta,
        ]);

        WaInboxMessageReceived::dispatch($inboxMessage->id);
        app(MobileNotificationService::class)->notifyWaInbox($inboxMessage);

        return true;
    }

    protected function helpMessageFor(string $topic): string
    {
        if ($topic === '') {
            return $this->helpOverviewMessage();
        }

        if (str_contains($topic, 'jadwal') || str_contains($topic, 'agenda') || str_contains($topic, 'kegiatan')) {
            return $this->helpAgendaMessage();
        }

        if (str_contains($topic, 'tl') || str_contains($topic, 'disposisi') || str_contains($topic, 'tindak lanjut')) {
            return $this->helpTlMessage();
        }

        if (str_contains($topic, 'surat')) {
            return $this->helpSuratMessage();
        }

        if (str_contains($topic, 'pajak') || str_contains($topic, 'kendaraan')) {
            return $this->helpPajakMessage();
        }

        return $this->helpOverviewMessage();
    }

    protected function helpOverviewMessage(): string
    {
        return implode("\n", [
            '*Bantuan Singkat*',
            'Pilih topik yang ingin kamu ketahui:',
            '1) Jadwal/Agenda → ketik: help jadwal',
            '2) Disposisi/TL → ketik: help tl',
            '3) Surat Keluar → ketik: help surat',
            '4) Pajak Kendaraan → ketik: help pajak',
        ]);
    }

    protected function helpAgendaMessage(): string
    {
        return implode("\n", [
            '*Topik Jadwal/Agenda*',
            '- jadwal hari ini',
            '- jadwal besok',
            '- jadwal belum disposisi hari ini',
            '- jadwal belum disposisi besok',
        ]);
    }

    protected function helpTlMessage(): string
    {
        return implode("\n", [
            '*Topik Disposisi/TL*',
            '- Format: TL-<kode> selesai',
            '- Contoh: TL-123 selesai',
        ]);
    }

    protected function helpSuratMessage(): string
    {
        return implode("\n", [
            '*Topik Surat Keluar*',
            '1) Ketik di grup: minta nomor surat keluar',
            '2) Aku balas via chat pribadi: minta kode klasifikasi (contoh 000.1)',
            '3) Balas dengan Hal Surat, lalu nomor dikirim kembali',
            'Ketik "batal" untuk membatalkan permintaan',
            'Jika 1 jam tidak ada respon, permintaan otomatis batal',
        ]);
    }

    protected function helpPajakMessage(): string
    {
        return implode("\n", [
            '*Topik Pajak Kendaraan*',
            '- Format: pajak-<plat> terbayar',
            '- Contoh: pajak-N1234AB terbayar',
        ]);
    }

    protected function sendHelpReply(array $payload, WaGatewayService $waGateway, string $message): bool
    {
        if (($payload['isGroup'] ?? false) === true) {
            $groupId = $this->extractGroupId($payload) ?? trim((string) ($payload['from'] ?? ''));
            if ($groupId !== '') {
                $result = $waGateway->sendTextToSpecificGroup($groupId, $message);

                return (bool) ($result['success'] ?? false);
            }
        }

        $sender = $this->extractSenderNumberForHelp($payload);
        if (! $sender) {
            return false;
        }

        $result = $waGateway->sendPersonalText([$sender], $message);

        return (bool) ($result['success'] ?? false);
    }

    protected function extractIncomingText(array $payload): ?string
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

    protected function extractSenderNumberForChat(array $payload): ?string
    {
        $candidates = [
            $payload['senderNumber'] ?? null,
            data_get($payload, 'sender.number'),
            data_get($payload, 'sender.phone'),
            data_get($payload, 'sender.user'),
            data_get($payload, 'sender.id'),
            data_get($payload, 'sender.id._serialized'),
            data_get($payload, 'sender.id.user'),
            data_get($payload, 'sender.jid'),
            data_get($payload, 'sender.remoteJid'),
            (string) ($payload['participant'] ?? ''),
            (string) ($payload['sender'] ?? ''),
            (string) ($payload['from'] ?? ''),
        ];

        $lidRaw = null;

        foreach ($candidates as $raw) {
            if (! is_string($raw) && ! is_numeric($raw)) {
                continue;
            }

            $raw = trim((string) $raw);

            if ($raw === '' || str_contains($raw, '@g.us')) {
                continue;
            }

            if (str_contains($raw, '@lid')) {
                $lidRaw = $raw;
                continue;
            }

            $normalized = PhoneNumber::normalize($raw);
            if ($normalized) {
                return $normalized;
            }
        }

        if ($lidRaw) {
            return $lidRaw;
        }

        return null;
    }

    protected function extractSenderName(array $payload): ?string
    {
        $candidates = [
            $payload['senderName'] ?? null,
            $payload['pushName'] ?? null,
            $payload['name'] ?? null,
            data_get($payload, 'sender.name'),
            data_get($payload, 'sender.pushName'),
            data_get($payload, 'sender.pushname'),
            data_get($payload, 'sender.verifiedName'),
            data_get($payload, 'sender.notify'),
            data_get($payload, 'contact.name'),
            data_get($payload, 'contact.pushName'),
            data_get($payload, 'contact.pushname'),
            data_get($payload, 'contact.notify'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function isBotCommandMessage(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        if (preg_match('/^help(\\s|$)/i', $message)) {
            return true;
        }

        if (str_contains($message, 'minta nomor surat')) {
            return true;
        }

        if (preg_match('/\\btl\\W*\\d+\\W*selesai\\b/i', $message)) {
            return true;
        }

        if (preg_match('/pajak-[a-z0-9 ]+\\s+terbayar/i', $message)) {
            return true;
        }

        if (preg_match('/\\b(cek|status)\\s+layanan/i', $message)) {
            return true;
        }

        if (preg_match('/\\bLP\\-[A-Za-z0-9\\-]+\\b/', $message)) {
            return true;
        }

        return false;
    }

    protected function isFromSelf(array $payload): bool
    {
        $flags = [
            $payload['fromMe'] ?? null,
            $payload['isFromMe'] ?? null,
            data_get($payload, 'message.fromMe'),
        ];

        foreach ($flags as $flag) {
            if ($flag === true || $flag === 1 || $flag === 'true') {
                return true;
            }
        }

        return false;
    }

    protected function extractSenderNumberForHelp(array $payload): ?string
    {
        $candidates = [
            (string) ($payload['participant'] ?? ''),
            (string) ($payload['sender'] ?? ''),
            (string) ($payload['from'] ?? ''),
        ];

        foreach ($candidates as $raw) {
            if ($raw === '' || str_contains($raw, '@g.us')) {
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

    protected function isHelpAuthorized(string $sender): bool
    {
        $personils = Personil::query()
            ->whereNotNull('no_wa')
            ->where('no_wa', '!=', '')
            ->get(['no_wa']);

        foreach ($personils as $personil) {
            if ($this->normalizeNumberFromDb($personil->no_wa) === $sender) {
                return true;
            }
        }

        return false;
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

        $allowedNumbers = $assignedNumbers
            ->merge($assignedFromDb)
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

    protected function allowedNumbersFromRoles(): array
    {
        $roles = $this->allowedRoles();
        $patterns = $this->allowedRolePatterns();

        if (empty($roles) && empty($patterns)) {
            return [];
        }

        $query = Personil::query()
            ->whereNotNull('no_wa')
            ->where('no_wa', '!=', '');

        $query->where(function ($builder) use ($roles, $patterns) {
            if (! empty($roles)) {
                $builder->whereIn('jabatan', $roles);
            }

            if (! empty($patterns)) {
                foreach ($patterns as $pattern) {
                    $builder->orWhere('jabatan', 'like', $pattern);
                }
            }
        });

        return $query
            ->pluck('no_wa')
            ->map(fn ($noWa) => $this->normalizeNumberFromDb($noWa))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function allowedNumbersFromConfig(): array
    {
        $setting = WaGatewaySetting::current();
        $raw = (string) ($setting->finish_whitelist ?? config('wa_gateway.finish_whitelist', ''));

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

    protected function normalizeGroupId(?string $groupId): ?string
    {
        $id = trim((string) ($groupId ?? ''));
        if ($id === '') {
            return null;
        }

        $normalized = preg_replace('/@.*/', '', $id) ?? '';

        return $normalized !== '' ? $normalized : null;
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
