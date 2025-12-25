<?php

namespace App\Console\Commands;

use App\Services\WaGatewayTokenSyncService;
use App\Models\WaGatewaySetting;
use Illuminate\Console\Command;

class SyncWaGatewayToken extends Command
{
    protected $signature = 'wa-gateway:sync-token
        {--session= : Session ID untuk memilih token tertentu}
        {--path= : Path device-registry.json lokal}
        {--url= : URL device-registry.json (remote)}
        {--dry-run : Hanya menampilkan token tanpa update}';

    protected $description = 'Sinkronisasi WA_GATEWAY_TOKEN dari wa-gateway device registry.';

    public function handle(WaGatewayTokenSyncService $service): int
    {
        $settings = WaGatewaySetting::current();
        $sessionId = $this->option('session') ?: ($settings->session_id ?? config('wa_gateway.session_id'));
        $path = $this->option('path');
        $url = $this->option('url');

        $record = $service->fetchDeviceRecord($sessionId, $path, $url);

        if (! $record) {
            if ($sessionId) {
                $this->error('Session ID tidak ditemukan di registry. Pastikan session ID cocok.');
            } else {
                $this->error('Device tidak ditemukan dari registry. Isi Session ID jika ada lebih dari satu device.');
            }

            return self::FAILURE;
        }

        $token = $service->fetchToken($sessionId, $path, $url);
        if (! $token) {
            $this->error('Token tidak ditemukan dari registry.');

            return self::FAILURE;
        }

        $current = (string) ($settings->token ?? config('wa_gateway.token'));

        $this->info('Token registry: ' . $token);
        $this->info('Token aplikasi: ' . ($current !== '' ? $current : '-'));

        if ($this->option('dry-run')) {
            $this->info('Dry-run: tidak ada perubahan.');

            return self::SUCCESS;
        }

        if ($current === $token) {
            $this->info('Token sudah sinkron.');

            return self::SUCCESS;
        }

        $dbOk = $service->updateDatabaseFromRecord($record);

        $this->info($dbOk ? 'Token database diperbarui.' : 'Tidak ada tabel DB yang cocok.');

        return self::SUCCESS;
    }
}
