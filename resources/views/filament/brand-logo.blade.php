@php
    $brandName = config('app.name', 'Jadwal');
    $variant = $variant ?? 'light';
    $isDark = $variant === 'dark';
    $logo = $isDark
        ? 'images/logo/logo-icon-64x64-dark.png'
        : 'images/logo/logo-icon-64x64.png';
    $textClass = $isDark ? 'text-gray-100' : 'text-gray-800';
@endphp

<div class="flex items-center gap-2">
    <img
        src="{{ asset($logo) }}"
        alt="Logo {{ $brandName }}"
        style="height: 2.25rem; width: auto;"
    >
    <span class="text-base font-semibold {{ $textClass }}">{{ $brandName }}</span>
</div>
