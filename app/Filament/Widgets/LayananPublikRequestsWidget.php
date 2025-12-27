<?php

namespace App\Filament\Widgets;

use App\Models\LayananPublikRequest;
use App\Support\RoleAccess;
use Filament\Widgets\Widget;

class LayananPublikRequestsWidget extends Widget
{
    protected static string $view = 'filament.widgets.layanan-publik-requests';

    protected int | string | array $columnSpan = 'full';

    public array $requests = [];

    public function mount(): void
    {
        $this->requests = LayananPublikRequest::query()
            ->with('layanan')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (LayananPublikRequest $request) {
                return [
                    'kode' => $request->kode_register,
                    'queue' => $request->queue_number,
                    'layanan' => $request->layanan?->nama ?? '-',
                    'kategori' => $request->layanan?->kategori,
                    'pemohon' => $request->nama_pemohon,
                    'status' => $request->status_label,
                    'created_at' => $request->created_at?->format('d/m/Y H:i'),
                ];
            })
            ->all();
    }

    public static function canView(): bool
    {
        return RoleAccess::canSeeNav(auth()->user(), 'filament.admin.resources.layanan-publik-register');
    }
}
