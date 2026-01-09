@php
    $brandName = config('app.name', 'Jadwal');
    $variant = $variant ?? 'light';
    $isDark = $variant === 'dark';
    $logo = $isDark
        ? 'images/logo/logo-icon-64x64-dark.png'
        : 'images/logo/logo-icon-64x64.png';
    $textClass = $isDark ? 'text-gray-100' : 'text-gray-800';
@endphp

<div class="fi-app-logo">
    <img
        src="{{ asset($logo) }}"
        alt="Logo {{ $brandName }}"
        class="fi-app-logo-image"
    >
    <span class="fi-app-logo-text {{ $textClass }}">{{ $brandName }}</span>
</div>
