<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-filament::button type="button" color="gray" wire:click="testConnection">
                Test Koneksi
            </x-filament::button>
            <x-filament::button type="button" color="warning" wire:click="restartQueueWorkers">
                Restart Worker/Queue
            </x-filament::button>
            <x-filament::button type="submit" color="primary">
                Simpan Pengaturan WA Gateway
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
