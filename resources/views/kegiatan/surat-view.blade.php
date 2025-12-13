<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Undangan - {{ $kegiatan->nomor ?? 'Tanpa Nomor' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Tailwind via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #0f172a;
        }
    </style>
</head>
<body class="min-h-screen text-slate-100 flex flex-col">

<header class="bg-slate-900/90 border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <div>
            <h1 class="text-sm md:text-base font-semibold">
                Surat Undangan
                @if($kegiatan->nomor)
                    — <span class="text-sky-300">{{ $kegiatan->nomor }}</span>
                @endif
            </h1>
            <p class="text-[11px] md:text-xs text-slate-300">
                {{ $kegiatan->nama_kegiatan ?? 'Kegiatan' }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-[11px]">
            <a href="{{ $fileUrl }}" target="_blank"
               class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-sky-600 hover:bg-sky-500">
                <span>🡕 Buka di tab baru</span>
            </a>
            <a href="{{ $fileUrl }}" download
               class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-600 hover:bg-emerald-500">
                <span>⬇ Unduh PDF</span>
            </a>
        </div>
    </div>
</header>

<main class="flex-1">
    <div class="max-w-5xl mx-auto px-2 md:px-4 py-3 md:py-4 h-[calc(100vh-80px)]">
        <iframe
            src="{{ $fileUrl }}"
            class="w-full h-full rounded-xl border border-slate-800 bg-slate-950"
        >
            Browser Anda tidak mendukung penampil PDF.
            Anda dapat mengunduh file di sini:
            <a href="{{ $fileUrl }}">Unduh PDF</a>.
        </iframe>
    </div>
</main>

<x-app-footer class="mt-auto" text-class="text-slate-400" />

</body>
</html>
