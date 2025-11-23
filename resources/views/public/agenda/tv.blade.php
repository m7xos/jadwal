<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Agenda Hari Ini - Layar TV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #020617; /* slate-950 */
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const intervalMs = 15000; // 15 detik
            const rowHeight = 160;    // tinggi kira-kira 1 kartu (px), silakan sesuaikan

            let offset = 0;

            setInterval(() => {
                const container = document.getElementById('agenda-container');
                if (!container) return;

                const maxScroll = container.scrollHeight - container.clientHeight;
                if (maxScroll <= 0) return;

                offset += rowHeight;
                if (offset > maxScroll + 50) {
                    offset = 0; // balik ke atas
                }

                container.scrollTo({
                    top: offset,
                    behavior: 'smooth',
                });
            }, intervalMs);
        });
    </script>
</head>
<body class="min-h-screen text-slate-50">

<div class="max-w-6xl mx-auto px-6 py-6 flex flex-col gap-4">
    {{-- Header --}}
    <header class="flex items-center justify-between">
        <div>
            <div class="text-xs text-sky-300 uppercase tracking-[0.2em]">Agenda Kegiatan</div>
            <h1 class="text-3xl font-bold">
                Hari Ini —
                {{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}
            </h1>
        </div>
        <div class="text-right text-xs text-sky-200">
            Layar TV Pelayanan<br>
            Update otomatis dari sistem
        </div>
    </header>

    <main id="agenda-container" class="mt-4 space-y-4 overflow-hidden" style="max-height: 70vh;">
        @if ($agendaToday->isEmpty())
            <div class="h-full flex items-center justify-center">
                <div class="text-2xl font-semibold text-slate-300">
                    Tidak ada agenda untuk hari ini.
                </div>
            </div>
        @else
            @foreach ($agendaToday as $kegiatan)
                <div class="rounded-2xl border border-sky-700/40 bg-sky-900/40 px-5 py-4 flex gap-4 items-start shadow-lg">
                    {{-- waktu besar di kiri --}}
                    <div class="w-40 text-right pr-4 border-r border-sky-700/60">
                        <div class="text-lg font-semibold text-sky-200">
                            {{ $kegiatan->waktu }}
                        </div>
                        <div class="text-xs text-sky-300 mt-1">
                            {{ optional($kegiatan->tanggal)->locale('id')->isoFormat('D MMM Y') }}
                        </div>
                        <div class="text-[11px] text-sky-400 mt-2">
                            No: {{ $kegiatan->nomor ?? '-' }}
                        </div>
                    </div>

                    {{-- isi utama --}}
                    <div class="flex-1">
                        <div class="text-2xl font-bold mb-1">
                            {{ $kegiatan->nama_kegiatan }}
                        </div>
                        <div class="text-lg text-sky-100 mb-2">
                            📍 {{ $kegiatan->tempat }}
                        </div>

                        @php
                            $personils = $kegiatan->personils ?? collect();
                        @endphp

                        @if ($personils->isNotEmpty())
                            <div class="text-sm text-sky-100">
                                👥
                                @foreach ($personils as $idx => $p)
                                    {{ $idx ? ' • ' : '' }}{{ $p->nama }}@if($p->jabatan) ({{ $p->jabatan }})@endif
                                @endforeach
                            </div>
                        @endif

                        @if ($kegiatan->keterangan)
                            <div class="text-sm text-slate-200 mt-2">
                                {{ $kegiatan->keterangan }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </main>

    <footer class="text-[11px] text-slate-500 mt-2">
        Halaman ini otomatis memuat agenda terbaru ketika petugas memperbarui data di sistem.
    </footer>
</div>

</body>
</html>
