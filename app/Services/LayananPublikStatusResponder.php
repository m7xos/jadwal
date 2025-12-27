<?php

namespace App\Services;

use App\Models\LayananPublikRequest;
use App\Support\PhoneNumber;

class LayananPublikStatusResponder
{
    public function handle(array $payload, WaGatewayService $waGateway): bool
    {
        $text = $this->extractText($payload);
        if ($text === null) {
            return false;
        }

        $kode = $this->extractRegisterCode($text);
        if (! $kode) {
            return false;
        }

        $request = LayananPublikRequest::query()
            ->with(['layanan', 'statusLogs' => fn ($query) => $query->latest()])
            ->where('kode_register', strtoupper($kode))
            ->first();

        if (! $request) {
            return $this->sendReply($payload, $waGateway, 'Kode register tidak ditemukan. Pastikan kode yang dikirim benar.');
        }

        $layanan = $request->layanan?->nama ?? 'Layanan Publik';
        $status = $request->status_label;
        $lastLog = $request->statusLogs->first();
        $lastUpdate = $lastLog?->created_at?->format('d/m/Y H:i') ?? $request->updated_at?->format('d/m/Y H:i');
        $url = url('/layanan/status/' . $request->kode_register);

        $message = "*Status Layanan Publik*\n"
            . "Kode: *{$request->kode_register}*\n"
            . "Layanan: *{$layanan}*\n"
            . "No Antrian: *" . ($request->queue_number ?? '-') . "*\n"
            . "Status: *{$status}*\n";

        if ($lastUpdate) {
            $message .= "Update terakhir: {$lastUpdate}\n";
        }

        $message .= "Cek detail: {$url}";

        return $this->sendReply($payload, $waGateway, $message);
    }

    protected function extractRegisterCode(string $text): ?string
    {
        if (preg_match('/\\b(?:cek|status)\\s+layanan(?:\\s+publik)?\\s*[:\\-]?\\s*([A-Za-z0-9\\-]+)/i', $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        if (preg_match('/\\b( LP\\-[A-Za-z0-9\\-]+ )\\b/ix', $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    protected function sendReply(array $payload, WaGatewayService $waGateway, string $message): bool
    {
        if (($payload['isGroup'] ?? false) === true) {
            $groupId = $this->extractGroupId($payload);
            if ($groupId) {
                $result = $waGateway->sendTextToSpecificGroup($groupId, $message);

                return (bool) ($result['success'] ?? false);
            }
        }

        $sender = $this->extractSender($payload);
        if (! $sender) {
            return false;
        }

        $result = $waGateway->sendPersonalText([$sender], $message);

        return (bool) ($result['success'] ?? false);
    }

    protected function extractSender(array $payload): ?string
    {
        $candidates = [
            (string) ($payload['participant'] ?? ''),
            (string) ($payload['sender'] ?? ''),
            (string) ($payload['from'] ?? ''),
        ];

        foreach ($candidates as $raw) {
            if ($raw === '' || str_contains($raw, '@g.us') || str_contains($raw, '@lid')) {
                continue;
            }

            $normalized = PhoneNumber::normalize($raw);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    protected function extractGroupId(array $payload): ?string
    {
        $group = $payload['group'] ?? null;

        if (is_array($group)) {
            foreach (['id', 'number', 'group_id'] as $key) {
                $value = trim((string) ($group[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $from = trim((string) ($payload['from'] ?? ''));
        if ($from !== '' && str_contains($from, '@g.us')) {
            return $from;
        }

        return null;
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
}
