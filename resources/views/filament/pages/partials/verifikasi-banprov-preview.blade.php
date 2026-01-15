<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
            <span class="font-medium text-gray-900">Preview Import</span>
            <span>Kecamatan: Watumalang</span>
            @if ($previewTahap)
                <span>Tahap: {{ $previewTahap }}</span>
            @endif
            <span>Total data: {{ $previewCount }}</span>
        </div>

        @if ($previewError)
            <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $previewError }}
            </div>
        @endif

        @if (empty($previewRows) && ! $previewError)
            <div class="mt-3 text-sm text-gray-500">
                Pilih file Excel untuk melihat ringkasan data yang akan diimport.
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

            <div class="mt-2 text-xs text-gray-500">
                Menampilkan {{ count($previewRows) }} dari {{ $previewCount }} data.
            </div>
        @endif
    </div>
</div>
