@php
    $showWaToast = \App\Filament\Widgets\WaInboxNotificationsWidget::canView();
    $showLayananToast = \App\Filament\Widgets\LayananPublikRequestsWidget::canView();
@endphp

@if ($showWaToast || $showLayananToast)
    <div class="fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none" style="left:auto;">
        @if ($showWaToast)
            @livewire(\App\Filament\Widgets\WaInboxNotificationsWidget::class, [], key('wa-inbox-toast'))
        @endif

        @if ($showLayananToast)
            @livewire(\App\Filament\Widgets\LayananPublikRequestsWidget::class, [], key('layanan-publik-toast'))
        @endif
    </div>
@endif
