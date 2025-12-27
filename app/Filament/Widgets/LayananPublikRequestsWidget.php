<?php

namespace App\Filament\Widgets;

use App\Models\LayananPublikRequest;
use App\Support\RoleAccess;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class LayananPublikRequestsWidget extends Widget
{
    protected string $view = 'filament.widgets.layanan-publik-requests';

    protected int | string | array $columnSpan = 'full';

    public function mount(): void
    {
        $this->initializeLastSeen();
    }

    public function refreshNotifications(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $cacheKey = $this->cacheKey($user->id);
        $lastSeen = Cache::get($cacheKey);

        $latest = LayananPublikRequest::query()
            ->with('layanan')
            ->latest('id')
            ->first();

        if (! $latest) {
            return;
        }

        if ($lastSeen === null) {
            Cache::put($cacheKey, $latest->id, now()->addDays(2));
            return;
        }

        if ($latest->id <= $lastSeen) {
            return;
        }

        $newCount = LayananPublikRequest::query()
            ->where('id', '>', $lastSeen)
            ->count();

        $layanan = $latest->layanan?->nama ?? 'Layanan Publik';
        $body = $newCount > 1
            ? "{$newCount} permohonan baru masuk. Terakhir: {$latest->kode_register} · {$layanan}"
            : "Kode: {$latest->kode_register} · Antrian: " . ($latest->queue_number ?? '-') . " · {$layanan}";

        Notification::make()
            ->title('Permohonan layanan baru')
            ->body($body)
            ->success()
            ->send();

        Cache::put($cacheKey, $latest->id, now()->addDays(2));
    }

    public static function canView(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.layanan-publik-register');
    }

    protected function initializeLastSeen(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $cacheKey = $this->cacheKey($user->id);
        if (Cache::has($cacheKey)) {
            return;
        }

        $latestId = LayananPublikRequest::query()->latest('id')->value('id');
        if ($latestId) {
            Cache::put($cacheKey, $latestId, now()->addDays(2));
        }
    }

    protected function cacheKey(int $userId): string
    {
        return 'layanan_publik_last_seen_' . $userId;
    }
}
