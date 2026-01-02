<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Status Pejabat Kecamatan Watumalang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: radial-gradient(circle at top left, #e0f2fe, #fef9c3, #f8fafc);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
<div class="min-h-screen flex flex-col">
    <header class="bg-white/90 border-b border-slate-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
                    Dashboard Status Pejabat
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Informasi keberadaan pejabat berdasarkan agenda hari ini.
                </p>
            </div>
            <div class="text-xs md:text-sm text-right">
                <div class="px-3 py-1 bg-slate-50 border border-slate-200 rounded-full inline-flex items-center gap-2">
                    <span class="text-slate-500">Tanggal:</span>
                    <span class="font-medium text-slate-800">
                        {{ $today->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-6xl mx-auto px-4 py-6 md:py-8 space-y-6">
            <section class="bg-white/80 border border-slate-200 rounded-2xl p-4 md:p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h2 class="text-lg md:text-xl font-semibold text-slate-800">Ringkasan Status</h2>
                        <p class="text-sm text-slate-600 mt-1">
                            Status dinas luar muncul jika ada agenda di luar kantor.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Di Kantor
                        </span>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                            Dinas Luar
                        </span>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                            Tidak diketahui
                        </span>
                    </div>
                </div>
                <div class="mt-3 text-xs text-slate-500">
                    Lokasi yang dianggap di kantor: Aula Kantor Kecamatan Lantai 2, Aula Kantor Kecamatan.
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($statuses as $item)
                    @php
                        $isDinasLuar = $item['status'] === 'Dinas Luar';
                        $isUnknown = $item['status'] === 'Tidak diketahui';
                        $badgeClass = $isUnknown
                            ? 'bg-slate-100 text-slate-600 border-slate-200'
                            : ($isDinasLuar ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200');
                    @endphp

                    <article class="bg-white/90 border border-slate-200 rounded-2xl p-4 md:p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base md:text-lg font-semibold text-slate-900">
                                    {{ $item['jabatan'] }}
                                </h3>
                                <p class="text-sm text-slate-600 mt-1">
                                    {{ $item['nama'] }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                                {{ $item['status'] }}
                            </span>
                        </div>

                        <div class="mt-4 text-sm text-slate-700">
                            @if(($item['kegiatan'] ?? collect())->isEmpty())
                                <div class="text-slate-500 text-sm">
                                    Tidak ada agenda hari ini.
                                </div>
                            @elseif(($item['kegiatan_luar'] ?? collect())->isNotEmpty())
                                <div class="text-xs font-semibold uppercase tracking-wide text-rose-500 mb-2">
                                    Agenda di luar kantor
                                </div>
                                <ul class="space-y-2 text-sm">
                                    @foreach($item['kegiatan_luar'] as $kegiatan)
                                        <li class="border border-rose-100 bg-rose-50/50 rounded-lg px-3 py-2">
                                            <div class="font-medium text-slate-800">
                                                {{ $kegiatan->nama_kegiatan ?? '-' }}
                                            </div>
                                            <div class="text-xs text-slate-500 mt-1">
                                                {{ $kegiatan->waktu ?? '-' }} - {{ $kegiatan->tempat ?? '-' }}
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-slate-500 text-sm">
                                    Di kantor. Tidak ada agenda dinas luar hari ini.
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        </div>
    </main>
</div>
</body>
</html>
