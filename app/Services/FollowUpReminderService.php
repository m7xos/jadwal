<?php

namespace App\Services;

use App\Models\FollowUpReminder;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FollowUpReminderService
{
    public const DEFAULT_INTERVAL_MINUTES = 30;

    public function send(FollowUpReminder $reminder): array
    {
        $reminder->loadMissing('user', 'personil', 'group.personils');

        /** @var WaGatewayService $waGateway */
        $waGateway = app(WaGatewayService::class);

        $result = $reminder->send_via === 'group'
            ? $this->sendToGroup($reminder, $waGateway)
            : $waGateway->sendPersonalText([$reminder->no_wa], $this->buildMessage($reminder));

        $now = Carbon::now();
        $success = (bool) ($result['success'] ?? false);

        $reminder->last_sent_at = $now;
        $reminder->sent_count = ($reminder->sent_count ?? 0) + 1;
        $reminder->next_send_at = $reminder->acknowledged_at
            ? null
            : $now->copy()->addMinutes(static::DEFAULT_INTERVAL_MINUTES);
        $reminder->last_response = $result['response'] ?? null;
        $reminder->last_error = $result['error'] ?? null;
        $reminder->status = $reminder->acknowledged_at ? 'acknowledged' : 'pending';
        $reminder->save();

        if (! $success) {
            Log::warning('Pengingat pekerjaan lain gagal dikirim', [
                'reminder_id' => $reminder->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }

        return $result;
    }

    public function buildMessage(FollowUpReminder $reminder): string
    {
        $tanggalLabel = $reminder->tanggal
            ? $reminder->tanggal->locale('id')->isoFormat('dddd, D MMMM Y')
            : '-';

        $jamLabel = $this->formatJam($reminder->jam);
        $tempat = trim((string) ($reminder->tempat ?? ''));

        $lines = [];
        $lines[] = '*PENGINGAT TINDAK LANJUT*';
        $lines[] = '';
        $lines[] = $this->formatLabel('Kegiatan', $reminder->nama_kegiatan);
        $lines[] = $this->formatLabel('Tanggal', $tanggalLabel);
        $lines[] = $this->formatLabel('Jam', $jamLabel);

        if ($tempat !== '') {
            $lines[] = $this->formatLabel('Tempat', $tempat);
        }

        $mention = $this->buildMention($reminder);
        if ($mention) {
            $lines[] = $this->formatLabel('Untuk', $mention);
        }

        $keterangan = trim((string) ($reminder->keterangan ?? ''));
        if ($keterangan !== '') {
            $lines[] = '';
            $lines[] = '*Keterangan:*';
            $lines[] = $keterangan;
        }

        $lines[] = $this->formatLabel('Kode', $reminder->reminder_code);
        $lines[] = '';
        $lines[] = 'Mohon tindak lanjuti kegiatan di atas.';
        $lines[] = 'Balas pesan ini dengan kata kunci *terima kasih* untuk menghentikan pengingat.';
        $lines[] = 'Jika ada banyak, bisa balas: *terima kasih 12* (kode) atau *terima kasih semua*.';

        $fallback = implode("\n", $lines);

        $tempatLine = $tempat !== ''
            ? $this->formatLabel('Tempat', $tempat) . "\n"
            : '';
        $penerimaLine = $mention
            ? $this->formatLabel('Untuk', $mention) . "\n"
            : '';
        $keteranganBlock = $keterangan !== ''
            ? "\n*Keterangan:*\n{$keterangan}\n"
            : '';

        $footer = implode("\n", [
            'Mohon tindak lanjuti kegiatan di atas.',
            'Balas pesan ini dengan kata kunci *terima kasih* untuk menghentikan pengingat.',
            'Jika ada banyak, bisa balas: *terima kasih 12* (kode) atau *terima kasih semua*.',
        ]);

        $data = [
            'kegiatan_line' => $this->formatLabel('Kegiatan', $reminder->nama_kegiatan ?? '-'),
            'tanggal_line' => $this->formatLabel('Tanggal', $tanggalLabel),
            'jam_line' => $this->formatLabel('Jam', $jamLabel),
            'tempat_line' => $tempatLine,
            'penerima_line' => $penerimaLine,
            'keterangan_block' => $keteranganBlock,
            'kode_line' => $this->formatLabel('Kode', $reminder->reminder_code),
            'footer' => $footer,
        ];

        /** @var WaMessageTemplateService $templateService */
        $templateService = app(WaMessageTemplateService::class);

        return $templateService->render('follow_up_reminder', $data, $fallback);
    }

    public function handleThanksReply(array $payload): bool
    {
        $rawMessage = trim((string) ($payload['message'] ?? ''));
        $normalizedMessage = strtolower(preg_replace('/\s+/', ' ', $rawMessage));

        $saysThanks = str_contains($normalizedMessage, 'terima kasih')
            || str_contains($normalizedMessage, 'terimakasih');

        if ($normalizedMessage === '' || ! $saysThanks) {
            return false;
        }

        $senderRaw = (string) ($payload['sender'] ?? $payload['from'] ?? '');
        $sender = PhoneNumber::normalize($senderRaw);

        if (! $sender) {
            return false;
        }

        $now = Carbon::now();
        $stopAll = str_contains($normalizedMessage, 'semua');

        if ($stopAll) {
            $reminders = FollowUpReminder::awaitingAck()
                ->where('normalized_no_wa', $sender)
                ->get();

            if ($reminders->isEmpty()) {
                return false;
            }

            foreach ($reminders as $reminder) {
                $this->acknowledgeReminder($reminder, $rawMessage, $now);
            }

            $this->sendThanksAllReply($sender, $reminders->count());

            return true;
        }

        $prId = $this->extractReminderId($normalizedMessage);

        $reminderQuery = FollowUpReminder::awaitingAck()
            ->where('normalized_no_wa', $sender);

        if ($prId) {
            $reminderQuery->where('id', $prId);
        }

        $reminder = $reminderQuery
            ->orderByDesc('id')
            ->first();

        if (! $reminder) {
            return false;
        }

        $this->acknowledgeReminder($reminder, $rawMessage, $now);
        $this->sendThanksReply($reminder);

        return true;
    }

    protected function sendThanksReply(FollowUpReminder $reminder): void
    {
        /** @var WaGatewayService $waGateway */
        $waGateway = app(WaGatewayService::class);

        $message = '*TERIMA KASIH*'
            . "\nPengingat untuk *{$reminder->nama_kegiatan}* sudah dihentikan."
            . "\nKode: *{$reminder->reminder_code}*";

        $waGateway->sendPersonalText([$reminder->no_wa], $message);
    }

    protected function extractReminderId(string $message): ?int
    {
        $matches = [];

        if (preg_match('/pr[^0-9]*(\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/teri?ma ?kasih[^0-9]*(\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function acknowledgeReminder(FollowUpReminder $reminder, string $rawMessage, Carbon $timestamp): void
    {
        $reminder->acknowledged_at = $timestamp;
        $reminder->next_send_at = null;
        $reminder->status = 'acknowledged';
        $reminder->last_error = null;
        $reminder->last_response = [
            'type' => 'thanks_reply',
            'received_at' => $timestamp->toDateTimeString(),
            'message' => $rawMessage,
        ];
        $reminder->save();
    }

    protected function sendThanksAllReply(string $normalizedNumber, int $count): void
    {
        /** @var WaGatewayService $waGateway */
        $waGateway = app(WaGatewayService::class);

        $message = '*TERIMA KASIH*'
            . "\n{$count} pengingat untuk nomor ini sudah dihentikan.";

        $waGateway->sendPersonalText([$normalizedNumber], $message);
    }

    protected function sendToGroup(FollowUpReminder $reminder, WaGatewayService $waGateway): array
    {
        $group = $reminder->group;

        if (! $group || ! $group->wa_gateway_group_id) {
            return [
                'success' => false,
                'error' => 'Grup belum dipilih atau belum memiliki ID WA Gateway.',
                'response' => null,
            ];
        }

        $message = $this->buildMessage($reminder);

        return $waGateway->sendTextToSpecificGroup($group->wa_gateway_group_id, $message);
    }

    protected function buildMention(FollowUpReminder $reminder): ?string
    {
        if ($reminder->send_via !== 'group') {
            return null;
        }

        $group = $reminder->group;

        if ($group) {
            $group->loadMissing('personils');
        }

        $target = $reminder->normalized_no_wa ?? PhoneNumber::normalize($reminder->no_wa);

        if ($group && $target) {
            $hasNumber = $group->personils
                ->filter()
                ->contains(fn ($personil) => PhoneNumber::normalize($personil->no_wa) === $target);

            if ($hasNumber) {
                return '@' . $target;
            }
        }

        $name = $this->recipientLabel($reminder);

        return $name !== '' ? $name : null;
    }

    protected function recipientLabel(FollowUpReminder $reminder): string
    {
        $personil = $reminder->personil;

        if ($personil) {
            $nama = trim((string) ($personil->nama ?? ''));
            $jabatan = trim((string) ($personil->jabatan ?? ''));

            if ($nama !== '' && $jabatan !== '') {
                return "{$nama} - {$jabatan}";
            }

            if ($nama !== '') {
                return $nama;
            }
        }

        return trim((string) ($reminder->user?->name ?? ''));
    }

    protected function formatLabel(string $label, string $value): string
    {
        return sprintf('%-10s: %s', $label, $value);
    }

    protected function formatJam(?string $raw): string
    {
        if (! $raw) {
            return '-';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $raw)->format('H:i') . ' WIB';
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('H:i', $raw)->format('H:i') . ' WIB';
            } catch (\Throwable) {
                return $raw;
            }
        }
    }
}
