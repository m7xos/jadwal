<x-filament::section heading="Permohonan Layanan Publik Terbaru">
    @if (empty($requests))
        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada permohonan baru.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500">
                    <tr>
                        <th class="py-2 pr-3">Kode</th>
                        <th class="py-2 pr-3">Antrian</th>
                        <th class="py-2 pr-3">Layanan</th>
                        <th class="py-2 pr-3">Pemohon</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Masuk</th>
                    </tr>
                </thead>
                <tbody class="text-gray-900 dark:text-white">
                    @foreach ($requests as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-3 font-semibold">{{ $row['kode'] }}</td>
                            <td class="py-2 pr-3">{{ $row['queue'] ?? '-' }}</td>
                            <td class="py-2 pr-3">
                                {{ $row['layanan'] }}
                                @if (! empty($row['kategori']))
                                    <span class="text-xs text-gray-500">({{ $row['kategori'] }})</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3">{{ $row['pemohon'] }}</td>
                            <td class="py-2 pr-3">{{ $row['status'] }}</td>
                            <td class="py-2 pr-3">{{ $row['created_at'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>
