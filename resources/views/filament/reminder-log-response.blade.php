<div class="space-y-2">
    @if (empty($response))
        <p class="text-sm text-gray-600">Tidak ada respons tersimpan.</p>
    @else
        <pre class="text-xs bg-gray-100 p-3 rounded-md overflow-auto max-h-96">{{ json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
</div>
