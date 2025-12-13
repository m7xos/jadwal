@props([
    'class' => '',
    'textClass' => 'text-slate-500',
])

<footer {{ $attributes->merge(['class' => "py-4 $class"]) }}>
    <div class="max-w-6xl mx-auto px-4 text-center text-[11px] md:text-xs {{ $textClass }}">
        <span class="font-semibold">Develop with ❤️ by Hardi Agunadi</span>
        <span class="mx-1">–</span>
        <span>Pranata Komputer Kec. Watumalang</span>
    </div>
</footer>
