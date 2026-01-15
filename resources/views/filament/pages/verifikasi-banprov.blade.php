<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" color="primary">
                Import Data
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8 space-y-4">
        <div class="rounded-lg border border-gray-200 p-4">
            <div class="text-sm text-gray-600">
                <span class="font-medium text-gray-900">Preview Import</span>
                <span class="ml-2">Kecamatan: Watumalang</span>
                @if ($previewTahap)
                    <span class="ml-2">Tahap: {{ $previewTahap }}</span>
                @endif
                <span class="ml-2">Total data: {{ $previewCount }}</span>
            </div>

            @if ($previewError)
                <div class="mt-3 text-sm text-danger-600">
                    {{ $previewError }}
                </div>
            @endif

            @if (! empty($previewRows))
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-700">
                                <th class="py-2 pr-4">Desa</th>
                                <th class="py-2 pr-4">No DPA</th>
                                <th class="py-2 pr-4">Jenis Kegiatan</th>
                                <th class="py-2 pr-4 text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach ($previewRows as $row)
                                <tr class="border-t border-gray-200">
                                    <td class="py-2 pr-4">{{ $row['desa'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['no_dpa'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['jenis_kegiatan'] }}</td>
                                    <td class="py-2 pr-4 text-right">
                                        {{ $row['jumlah'] ? number_format($row['jumlah'], 0, ',', '.') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
