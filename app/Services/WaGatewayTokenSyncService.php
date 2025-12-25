<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Models\WaGatewaySetting;

class WaGatewayTokenSyncService
{
    /**
     * @return array<string, mixed>|null
     */
    public function fetchDeviceRecord(?string $sessionId = null, ?string $path = null, ?string $url = null): ?array
    {
        $records = $this->loadRegistry($path, $url);

        if (empty($records)) {
            return null;
        }

        $sessionId = trim((string) ($sessionId ?? ''));

        if ($sessionId !== '') {
            foreach ($records as $record) {
                if (($record['sessionId'] ?? null) === $sessionId) {
                    return $record;
                }
            }
            return null;
        }

        if (count($records) === 1) {
            return $records[0];
        }

        return null;
    }

    public function fetchToken(?string $sessionId = null, ?string $path = null, ?string $url = null): ?string
    {
        $record = $this->fetchDeviceRecord($sessionId, $path, $url);

        if (! $record) {
            return null;
        }

        return $this->extractToken($record);
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
     * Update token (apiKey) + optional master key from registry record.
     *
     * @param array<string, mixed> $record
     */
    public function updateDatabaseFromRecord(array $record): bool
    {
        if (! Schema::hasTable('wa_gateway_settings')) {
            return false;
        }

        $token = $this->extractToken($record);
        if (! $token) {
            return false;
        }

        $updates = ['token' => $token];

        $sessionId = trim((string) ($record['sessionId'] ?? ''));
        if ($sessionId !== '' && Schema::hasColumn('wa_gateway_settings', 'session_id')) {
            $updates['session_id'] = $sessionId;
        }

        $masterKey = trim((string) ($record['key'] ?? $record['masterKey'] ?? $record['master_key'] ?? $record['masterkey'] ?? ''));
        if ($masterKey !== '' && Schema::hasColumn('wa_gateway_settings', 'key')) {
            $updates['key'] = $masterKey;
        }

        $secretKey = trim((string) ($record['secretKey'] ?? $record['secret_key'] ?? $record['secretkey'] ?? ''));
        if ($secretKey !== '' && Schema::hasColumn('wa_gateway_settings', 'secret_key')) {
            $updates['secret_key'] = $secretKey;
        }

        if (Schema::hasColumn('wa_gateway_settings', 'api_key')) {
            $updates['api_key'] = $token;
        }

        return (bool) \DB::table('wa_gateway_settings')->updateOrInsert(['id' => 1], $updates);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadRegistry(?string $path = null, ?string $url = null): array
    {
        $settings = WaGatewaySetting::current();
        $path = $path ?: ($settings->registry_path ?? config('wa_gateway.registry_path'));
        $url = $url ?: ($settings->registry_url ?? config('wa_gateway.registry_url'));

        if ($url) {
            $headers = [];
            $token = trim((string) ($settings->registry_token ?? config('wa_gateway.registry_token', '')));
            $user = trim((string) ($settings->registry_user ?? config('wa_gateway.registry_user', '')));
            $pass = (string) ($settings->registry_pass ?? config('wa_gateway.registry_pass', ''));

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

        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        $records = [];
        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! isset($value['sessionId']) && (is_string($key) || is_int($key))) {
                $value['sessionId'] = (string) $key;
            }

            $records[] = $value;
        }

        return $records;
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
