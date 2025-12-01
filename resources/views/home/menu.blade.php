<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Menu Aplikasi Kantor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Tailwind untuk tampilan cepat --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: radial-gradient(circle at top left, #e0f2fe, #eef2ff, #fef9c3);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">

<div class="min-h-screen flex flex-col">
    {{-- Header --}}
    <header class="bg-gradient-to-r from-sky-600 to-indigo-600 text-white shadow-md">
        <div class="max-w-5xl mx-auto px-4 py-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
                    Selamat datang
                </h1>
                <p class="text-sm md:text-base text-sky-100 mt-1">
                    Silakan pilih aplikasi yang ingin digunakan.
                </p>
            </div>
            <div class="text-xs text-sky-100 md:text-right">
                {{ now()->locale('id')->isoFormat('dddd, D MMMM Y HH:mm') }} WIB
            </div>
        </div>
    </header>

    {{-- Konten --}}
    <main class="flex-1">
        <div class="max-w-5xl mx-auto px-4 py-8 md:py-10">
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {{-- Kartu: Pengingat Audio --}}
                <a href="{{ route('pengingat.audio') }}"
                   class="group bg-white/90 border border-slate-200 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition p-5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <div class="h-10 w-10 rounded-xl bg-sky-100 flex items-center justify-center">
                            <span class="text-sky-600 text-xl">🔊</span>
                        </div>
                        <span class="text-[11px] px-2 py-1 rounded-full bg-sky-50 text-sky-700 border border-sky-100">
                            Layar TV / Speaker
                        </span>
                    </div>
                    <h2 class="text-lg font-semibold mb-1 text-slate-900 group-hover:text-sky-700">
                        Pengingat Audio Apel & Presensi
                    </h2>
                    <p class="text-sm text-slate-600 flex-1">
                        Memutar audio pengingat apel pagi dan presensi secara otomatis sesuai jadwal.
                        Cocok dipasang di komputer yang terhubung ke TV pelayanan dan speaker.
                    </p>
                    <div class="mt-4 text-xs text-slate-500 flex items-center justify-between">
                        <span>Jadwal: Senin–Kamis & Jumat</span>
                        <span class="inline-flex items-center gap-1 text-sky-600">
                            Buka
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 17L17 7"/>
                                <path d="M7 7h10v10"/>
                            </svg>
                        </span>
                    </div>
                </a>

                {{-- Kartu: Agenda Kegiatan Publik --}}
                <a href="{{ route('public.agenda.index') }}"
                   class="group bg-white/90 border border-slate-200 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition p-5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <div class="h-10 w-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                            <span class="text-emerald-600 text-xl">📅</span>
                        </div>
                        <span class="text-[11px] px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                            Publik
                        </span>
                    </div>
                    <h2 class="text-lg font-semibold mb-1 text-slate-900 group-hover:text-emerald-700">
                        Agenda Kegiatan Kantor
                    </h2>
                    <p class="text-sm text-slate-600 flex-1">
                        Menampilkan daftar agenda kegiatan kantor yang dapat diakses oleh masyarakat,
                        lengkap dengan tanggal, waktu, tempat, dan personil yang menghadiri.
                    </p>
                    <div class="mt-4 text-xs text-slate-500 flex items-center justify-between">
                        <span>Mode tampilan web biasa</span>
                        <span class="inline-flex items-center gap-1 text-emerald-600">
                            Buka
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 17L17 7"/>
                                <path d="M7 7h10v10"/>
                            </svg>
                        </span>
                    </div>
                </a>

                {{-- Kartu: Agenda Kegiatan TV Mode (kalau sudah ada route-nya) --}}
                <a href="{{ url('/agenda-kegiatan-tv') }}"
                   class="group bg-white/90 border border-slate-200 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition p-5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <div class="h-10 w-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                            <span class="text-indigo-600 text-xl">📺</span>
                        </div>
                        <span class="text-[11px] px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                            Mode TV
                        </span>
                    </div>
                    <h2 class="text-lg font-semibold mb-1 text-slate-900 group-hover:text-indigo-700">
                        Agenda Kegiatan – Layar TV
                    </h2>
                    <p class="text-sm text-slate-600 flex-1">
                        Tampilan khusus layar besar dengan teks besar dan auto-scroll,
                        cocok untuk ditayangkan di TV pelayanan.
                    </p>
                    <div class="mt-4 text-xs text-slate-500 flex items-center justify-between">
                        <span>Disarankan fullscreen</span>
                        <span class="inline-flex items-center gap-1 text-indigo-600">
                            Buka
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 17L17 7"/>
                                <path d="M7 7h10v10"/>
                            </svg>
                        </span>
                    </div>
                </a>

                @if (config('app.show_admin_panel_menu'))
                    {{-- Kartu: Panel Admin / Filament --}}
                    <a href="{{ url('/admin') }}"
                       class="group bg-white/90 border border-slate-200 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition p-5 flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <div class="h-10 w-10 rounded-xl bg-amber-100 flex items-center justify-center">
                                <span class="text-amber-600 text-xl">🛠️</span>
                            </div>
                            <span class="text-[11px] px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-100">
                                Internal
                            </span>
                        </div>
                        <h2 class="text-lg font-semibold mb-1 text-slate-900 group-hover:text-amber-700">
                            Panel Admin Agenda Kegiatan
                        </h2>
                        <p class="text-sm text-slate-600 flex-1">
                            Digunakan oleh petugas internal untuk mengelola data agenda kegiatan,
                            personil, dan pengiriman pesan WhatsApp.
                        </p>
                        <div class="mt-4 text-xs text-slate-500 flex items-center justify-between">
                            <span>Perlu login</span>
                            <span class="inline-flex items-center gap-1 text-amber-600">
                                Buka
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17L17 7"/>
                                    <path d="M7 7h10v10"/>
                                </svg>
                            </span>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-white/80">
        <div class="max-w-5xl mx-auto px-4 py-3 text-[11px] md:text-xs text-slate-500 flex flex-col md:flex-row md:items-center md:justify-between gap-1">
            <div>
                &copy; {{ date('Y') }} — Sistem Aplikasi Kantor.
            </div>
            <div class="text-slate-400">
                Pilih aplikasi yang sesuai dengan kebutuhan penggunaan di kantor.
            </div>
        </div>
    </footer>
</div>

</body>
</html>
