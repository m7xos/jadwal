<form action="{{ route('yieldpanel.pref') }}" method="POST" class="px-4 pb-3 border-t border-gray-100 dark:border-gray-800">
    @csrf
    <div class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Tema Yield Panel</div>
    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="colors" value="1" @checked($pref['colors']) class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
        Warna disarankan
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 mt-1.5">
        <input type="checkbox" name="font" value="1" @checked($pref['font']) class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
        Font Inter (tema)
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 mt-1.5">
        <input type="checkbox" name="icons" value="1" @checked($pref['icons']) class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
        Ikon Phosphor
    </label>
    <div class="mt-2">
        <button type="submit" class="w-full text-xs font-semibold px-2 py-1.5 rounded bg-primary-600 text-white hover:bg-primary-700">
            Terapkan
        </button>
    </div>
</form>
