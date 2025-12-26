<x-filament-panels::page>
    <form wire:submit.prevent="refreshLists" class="space-y-6">
        {{ $this->form }}
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Nomor Surat Tersedia">
            <textarea
                class="w-full rounded-lg border-none bg-gray-100/70 p-3 text-sm text-gray-900 dark:bg-white/5 dark:text-white"
                rows="12"
                readonly
            >{{ $availableList }}</textarea>
        </x-filament::section>

        <x-filament::section heading="Nomor Surat Dibooking">
            <textarea
                class="w-full rounded-lg border-none bg-gray-100/70 p-3 text-sm text-gray-900 dark:bg-white/5 dark:text-white"
                rows="12"
                readonly
            >{{ $bookedList }}</textarea>
        </x-filament::section>
    </div>
</x-filament-panels::page>
