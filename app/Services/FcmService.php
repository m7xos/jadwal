<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * @param array<int, string> $tokens
     * @param array<string, mixed> $data
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return;
        }

        $serverKey = (string) config('fcm.server_key');
        $endpoint = (string) config('fcm.endpoint');

        if ($serverKey === '' || $endpoint === '') {
            Log::warning('FCM config belum lengkap, notifikasi di-skip.');
            return;
        }

        $headers = [
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ];

        $senderId = (string) config('fcm.sender_id');
        if ($senderId !== '') {
            $headers['Sender'] = 'id=' . $senderId;
        }

        foreach (array_chunk($tokens, 1000) as $chunk) {
            $payload = [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'priority' => 'high',
            ];

            try {
                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post($endpoint, $payload);

                if (! $response->successful()) {
                    Log::warning('FCM kirim gagal', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM kirim exception', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
