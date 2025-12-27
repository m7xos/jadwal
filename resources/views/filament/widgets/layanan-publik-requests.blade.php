<div
    x-data="{ open: true }"
    x-show="open"
    x-transition.opacity.duration.200ms
    class="fixed bottom-5 right-5 z-50 w-full max-w-sm"
>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-white">
                    Permohonan Layanan Publik Terbaru
                </p>
                <p class="text-xs text-slate-500 dark:text-gray-400">
                    {{ count($requests) }} permohonan terbaru
                </p>
            </div>
            <button
                type="button"
                class="text-slate-400 hover:text-slate-600 dark:hover:text-white"
                aria-label="Tutup"
                @click="open = false"
            >
                ✕
            </button>
        </div>

        <div class="max-h-80 overflow-y-auto px-4 py-3">
            @if (empty($requests))
                <p class="text-sm text-slate-500 dark:text-gray-400">Belum ada permohonan baru.</p>
            @else
                <div class="space-y-3 text-sm text-slate-700 dark:text-gray-200">
                    @foreach ($requests as $row)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">{{ $row['kode'] }}</span>
                                <span class="text-xs text-slate-500 dark:text-gray-400">Antrian {{ $row['queue'] ?? '-' }}</span>
                            </div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-gray-400">
                                {{ $row['layanan'] }}
                                @if (! empty($row['kategori']))
                                    ({{ $row['kategori'] }})
                                @endif
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span>{{ $row['pemohon'] }}</span>
                                <span class="text-slate-500 dark:text-gray-400">{{ $row['created_at'] ?? '-' }}</span>
                            </div>
                            <div class="mt-1 text-xs font-medium text-slate-700 dark:text-gray-200">
                                Status: {{ $row['status'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="border-t border-slate-200 px-4 py-2 text-right text-xs dark:border-gray-800">
            <a
                href="{{ route('filament.admin.resources.layanan-publik-register.index') }}"
                class="text-sky-600 hover:text-sky-700"
            >
                Lihat semua
            </a>
        </div>
    </div>
</div>
