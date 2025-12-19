<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class WaGatewayTokenSyncService
{
    public function fetchToken(?string $sessionId = null, ?string $path = null, ?string $url = null): ?string
    {
        $records = $this->loadRegistry($path, $url);

        if (empty($records)) {
            return null;
        }

        $sessionId = trim((string) ($sessionId ?? ''));

        if ($sessionId !== '') {
            foreach ($records as $record) {
                if (($record['sessionId'] ?? null) === $sessionId) {
                    return $this->extractToken($record);
                }
            }
        }

        if (count($records) === 1) {
            return $this->extractToken($records[0]);
        }

        return $this->extractToken($records[0]);
    }

    public function updateEnvToken(string $token): bool
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return false;
        }

        $contents = File::get($envPath);
        $pattern = "/^WA_GATEWAY_TOKEN=.*/m";

        if (preg_match($pattern, $contents)) {
            $updated = preg_replace($pattern, 'WA_GATEWAY_TOKEN=' . $token, $contents);
        } else {
            $updated = rtrim($contents) . PHP_EOL . 'WA_GATEWAY_TOKEN=' . $token . PHP_EOL;
        }

        if ($updated === null) {
            return false;
        }

        File::put($envPath, $updated);

        // Refresh runtime config cache when possible.
        try {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($envPath, true);
            }
        } catch (\Throwable) {
            // ignore
        }

        return true;
    }

    public function updateDatabaseToken(string $token): bool
    {
        if (! Schema::hasTable('wa_gateway_settings')) {
            return false;
        }

        $table = 'wa_gateway_settings';

        if (Schema::hasColumn($table, 'token')) {
            return (bool) \DB::table($table)->updateOrInsert(['id' => 1], ['token' => $token]);
        }

        if (Schema::hasColumn($table, 'api_key')) {
            return (bool) \DB::table($table)->updateOrInsert(['id' => 1], ['api_key' => $token]);
        }

        if (Schema::hasColumn($table, 'key') && Schema::hasColumn($table, 'value')) {
            return (bool) \DB::table($table)->updateOrInsert(['key' => 'WA_GATEWAY_TOKEN'], ['value' => $token]);
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadRegistry(?string $path = null, ?string $url = null): array
    {
        $path = $path ?: config('wa_gateway.registry_path');
        $url = $url ?: config('wa_gateway.registry_url');

        if ($url) {
            $headers = [];
            $token = trim((string) config('wa_gateway.registry_token', ''));
            $user = trim((string) config('wa_gateway.registry_user', ''));
            $pass = (string) config('wa_gateway.registry_pass', '');

            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            } elseif ($user !== '' || $pass !== '') {
                $headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
            }

            $response = Http::timeout(5)
                ->retry(3, 300)
                ->withHeaders($headers)
                ->get($url);
            if ($response->successful()) {
                $data = $response->json();
                return is_array($data) ? $data : [];
            }
        }

        if (! $path || ! File::exists($path)) {
            return [];
        }

        $raw = File::get($path);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function extractToken(array $record): ?string
    {
        $token = trim((string) ($record['token'] ?? $record['apiKey'] ?? ''));

        return $token !== '' ? $token : null;
    }
}
