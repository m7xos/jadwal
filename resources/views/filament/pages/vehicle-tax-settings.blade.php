<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" color="primary">
                Simpan Pengurus Barang
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
