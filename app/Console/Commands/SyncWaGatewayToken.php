<?php

namespace App\Console\Commands;

use App\Services\WaGatewayTokenSyncService;
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
        $sessionId = $this->option('session') ?: config('wa_gateway.session_id');
        $path = $this->option('path');
        $url = $this->option('url');

        $token = $service->fetchToken($sessionId, $path, $url);

        if (! $token) {
            $this->error('Token tidak ditemukan dari registry.');

            return self::FAILURE;
        }

        $current = (string) config('wa_gateway.token');

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

        $envOk = $service->updateEnvToken($token);
        $dbOk = $service->updateDatabaseToken($token);

        $this->info($envOk ? 'WA_GATEWAY_TOKEN di .env diperbarui.' : 'Gagal memperbarui .env.');
        $this->info($dbOk ? 'Token database diperbarui.' : 'Tidak ada tabel DB yang cocok.');

        return self::SUCCESS;
    }
}
