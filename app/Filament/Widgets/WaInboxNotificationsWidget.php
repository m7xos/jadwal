<?php

namespace App\Filament\Widgets;

use App\Models\WaInboxMessage;
use App\Support\RoleAccess;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class WaInboxNotificationsWidget extends Widget
{
    protected string $view = 'filament.widgets.wa-inbox-notifications';

    protected int | string | array $columnSpan = 'full';

    protected $listeners = [
        'echo-private:wa-inbox,WaInboxMessageReceived' => 'refreshNotifications',
    ];

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

        $latest = WaInboxMessage::query()
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

        $newCount = WaInboxMessage::query()
            ->where('id', '>', $lastSeen)
            ->count();

        $senderLabel = $latest->sender_name ?: $latest->sender_number;
        $body = $newCount > 1
            ? "{$newCount} chat masuk baru. Terakhir dari {$senderLabel}."
            : "Dari: {$senderLabel}";

        Notification::make()
            ->title('Chat WA masuk')
            ->body($body)
            ->warning()
            ->send();

        Cache::put($cacheKey, $latest->id, now()->addDays(2));
    }

    public static function canView(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.wa-inbox-messages');
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

        $latestId = WaInboxMessage::query()->latest('id')->value('id');
        if ($latestId) {
            Cache::put($cacheKey, $latestId, now()->addDays(2));
        }
    }

    protected function cacheKey(int $userId): string
    {
        return 'wa_inbox_last_seen_' . $userId;
    }
}
