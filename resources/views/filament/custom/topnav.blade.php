@php
    use Filament\Navigation\NavigationGroup;
    use Filament\Navigation\NavigationItem;
    use Illuminate\Support\Str;

    /** @var array<int, NavigationGroup> $navigation */
    $order = [
        'Manajemen Kegiatan' => 1,
        'Laporan' => 2,
        'Pengaturan' => 3,
        'Log' => 4,
        'Halaman Publik' => 5,
    ];

    $groups = collect($navigation)
        ->map(function (NavigationGroup $group) {
            return [
                'label' => $group->getLabel() ?? 'Lainnya',
                'items' => collect($group->getItems())
                    ->filter(fn (NavigationItem $item) => $item->isVisible())
                    ->sortBy([
                        fn (NavigationItem $item) => $item->getSort() === -1 ? 999 : $item->getSort(),
                        fn (NavigationItem $item) => $item->getLabel(),
                    ])
                    ->values(),
            ];
        })
        ->filter(fn ($group) => $group['items']->isNotEmpty());

    $sorted = collect($order)
        ->map(function ($pos, $label) use ($groups) {
            return $groups->firstWhere('label', $label);
        })
        ->filter()
        ->merge(
            $groups->filter(fn ($group) => ! array_key_exists($group['label'], $order))
                ->sortBy('label')
                ->values()
        )
        ->values();
@endphp

<div class="hidden md:flex flex-row items-center gap-4 text-sm leading-tight">
    @foreach ($sorted as $group)
        <details class="relative group">
            <summary class="cursor-pointer text-sm font-medium text-gray-700 flex items-center gap-1 list-none px-1 py-1 rounded hover:bg-gray-100">
                {{ $group['label'] }}
                <svg class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                </svg>
            </summary>

            <div class="absolute mt-1 w-56 bg-white shadow-lg rounded-md ring-1 ring-gray-200 py-1.5 z-50">
                @foreach ($group['items'] as $item)
                    @php /** @var NavigationItem $item */ @endphp
                    <a
                        href="{{ $item->getUrl() }}"
                        @if ($item->shouldOpenUrlInNewTab()) target="_blank" rel="noopener" @endif
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
                    >
                        @if ($icon = $item->getIcon())
                            <x-filament::icon :icon="$icon" class="w-4 h-4 text-gray-500" />
                        @endif
                        <span>{{ $item->getLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
