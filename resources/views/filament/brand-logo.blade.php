@php
    $brandName = config('app.name', 'Jadwal');
    $variant = $variant ?? 'light';
    $isDark = $variant === 'dark';
    $logo = $isDark
        ? 'images/logo/logo-icon-64x64-dark.png'
        : 'images/logo/logo-icon-64x64.png';
    $loginLogo = $isDark
        ? 'images/logo/android-512x512-dark.png'
        : 'images/logo/android-512x512.png';
    $textClass = $isDark ? 'text-gray-100' : 'text-gray-800';
@endphp

<div class="fi-app-logo">
    <img
        src="{{ asset($logo) }}"
        alt="Logo {{ $brandName }}"
        class="fi-app-logo-image fi-app-logo-image-default"
    >
    <img
        src="{{ asset($loginLogo) }}"
        alt="Logo {{ $brandName }}"
        class="fi-app-logo-image fi-app-logo-image-login"
    >
    <span class="fi-app-logo-text {{ $textClass }}">{{ $brandName }}</span>
</div>
