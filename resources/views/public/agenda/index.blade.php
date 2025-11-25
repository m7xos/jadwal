<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Agenda Kegiatan Kecamatan Watumalang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: linear-gradient(180deg, #e0f2fe 0%, #f8fafc 40%, #fefce8 100%);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">

<div class="min-h-screen flex flex-col">
    {{-- HEADER --}}
    <header class="bg-white/95 border-b border-slate-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-sky-800">
                    Agenda Kegiatan Kecamatan Watumalang
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Informasi kegiatan resmi kecamatan untuk masyarakat.
                </p>
            </div>
            <div class="text-xs md:text-sm text-right">
                <div class="px-3 py-1 bg-sky-50 border border-sky-200 rounded-full inline-flex items-center gap-2">
                    <span class="text-sky-500">📅</span>
                    <span class="font-medium text-sky-800">
                        {{ $today->locale('id')->isoFormat('dddd, D MMMM Y') }}
                    </span>
                </div>
            </div>
        </div>
    </header>

    {{-- CONTENT --}}
    <main class="flex-1">
        <div class="max-w-6xl mx-auto px-4 py-6 md:py-8 space-y-8">

            {{-- AGENDA HARI INI & MENDATANG --}}
            <section>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xl md:text-2xl font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 rounded-full bg-emerald-100 items-center justify-center text-emerald-600">
                            📌
                        </span>
                        Agenda Hari Ini & Mendatang
                    </h2>
				  
				    {{-- Filter rentang tanggal --}}
				<form method="GET" action="{{ route('public.agenda.index') }}"
					  class="flex flex-col md:flex-row md:items-center gap-2 text-xs md:text-sm bg-white/70 px-3 py-2 rounded-xl border border-slate-200">
					<div class="flex items-center gap-2">
						<span class="text-slate-600 whitespace-nowrap font-medium">
							Rentang tanggal:
						</span>

						{{-- Tanggal mulai --}}
						<input
							type="date"
							id="tanggal_mulai"
							name="tanggal_mulai"
							value="{{ request('tanggal_mulai', ($startDate ?? $today)->toDateString()) }}"
							class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs md:text-sm bg-white
								   focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400"
						/>

						<span class="text-slate-500">s/d</span>

						{{-- Tanggal selesai --}}
						<input
							type="date"
							id="tanggal_selesai"
							name="tanggal_selesai"
							value="{{ request('tanggal_selesai') }}"
							class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs md:text-sm bg-white
								   focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400"
						/>
					</div>

					<div class="flex items-center gap-2 justify-end md:justify-start">
						<button
							type="submit"
							class="px-3 py-1.5 rounded-lg bg-sky-600 text-white font-medium hover:bg-sky-500
								   shadow-sm transition text-xs md:text-sm"
						>
							Terapkan
						</button>

						<a
							href="{{ route('public.agenda.index') }}"
							class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 bg-white
								   hover:bg-slate-100 text-xs md:text-sm"
						>
							Reset
						</a>
					</div>

					@if($startDate || $endDate)
						<div class="text-[11px] text-slate-500 md:ml-2">
							Menampilkan agenda
							@if($startDate)
								mulai <span class="font-medium">
									{{ $startDate->locale('id')->isoFormat('D MMM Y') }}
								</span>
							@endif
							@if($endDate)
								sampai <span class="font-medium">
									{{ $endDate->locale('id')->isoFormat('D MMM Y') }}
								</span>
							@endif
						</div>
					@endif
				</form>
                </div>

                @if($upcoming->isEmpty())
                    <div class="bg-white/80 border border-dashed border-slate-300 rounded-xl px-4 py-5 text-sm text-slate-600">
                        Belum ada agenda yang terjadwal hari ini dan ke depan.
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($upcoming as $kegiatan)
                            @php
                                $isToday = optional($kegiatan->tanggal)->isSameDay($today);
                            @endphp

                            <div class="
                                rounded-2xl border shadow-sm p-4 md:p-5 bg-white/90
                                {{ $isToday ? 'border-emerald-300 ring-1 ring-emerald-200' : 'border-slate-200' }}
                            ">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-1">
                                    <div class="flex items-center gap-2">
                                        @if($isToday)
                                            <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold tracking-wide rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200">
                                                HARI INI
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium tracking-wide rounded-full bg-sky-50 text-sky-700 border border-sky-200">
                                                Agenda Mendatang
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Nomor: <span class="font-mono">{{ $kegiatan->nomor ?? '-' }}</span>
                                    </div>
                                </div>

                                <div class="text-sm text-slate-600 mb-2">
                                    {{ optional($kegiatan->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                                </div>

                                <h3 class="text-base md:text-lg font-semibold text-slate-900 mb-2">
                                    {{ $kegiatan->nama_kegiatan }}
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400">Waktu</div>
                                        <div class="font-medium text-slate-800">
                                            {{ $kegiatan->waktu ?? '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400">Tempat</div>
                                        <div class="font-medium text-slate-800">
                                            {{ $kegiatan->tempat ?? '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400">Status</div>
                                        <div class="font-medium">
                                            @if(($kegiatan->sudah_disposisi ?? false) === true)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs border border-emerald-200">
                                                    ✔ Sudah disposisi
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs border border-amber-200">
                                                    ⏳ Menunggu disposisi
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($kegiatan->personils && $kegiatan->personils->isNotEmpty())
                                    <div class="mt-3 text-sm">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-1">
                                            Personil yang ditugaskan
                                        </div>
                                        <ul class="list-disc list-inside space-y-0.5 text-slate-700">
                                            @foreach($kegiatan->personils as $p)
                                                <li>
                                                    {{ $p->nama }}
                                                    @if($p->jabatan)
                                                        <span class="text-slate-400 text-xs">
                                                            ({{ $p->jabatan }})
                                                        </span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if($kegiatan->keterangan)
                                    <div class="mt-3 text-sm">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-1">
                                            Keterangan
                                        </div>
                                        <div class="text-slate-700 leading-relaxed">
                                            {{ $kegiatan->keterangan }}
                                        </div>
                                    </div>
                                @endif

                                @if($kegiatan->surat_undangan)
                                    <div class="mt-3 text-xs">
                                        <a href="{{ \Illuminate\Support\Facades\URL::to(\Illuminate\Support\Facades\Storage::disk('public')->url($kegiatan->surat_undangan)) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-sky-600 hover:bg-sky-500 text-white font-medium shadow-sm">
                                            📎 Lihat Surat (PDF)
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- R I W A Y A T  A G E N D A --}}
            <section>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xl md:text-2xl font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 rounded-full bg-slate-100 items-center justify-center text-slate-600">
                            📚
                        </span>
                        Riwayat Agenda Terakhir
                    </h2>
                    <span class="text-[11px] text-slate-500">
                        Maksimal 20 agenda terakhir yang sudah lewat.
                    </span>
                </div>

                @if($past->isEmpty())
                    <div class="bg-white/80 border border-dashed border-slate-300 rounded-xl px-4 py-5 text-sm text-slate-600">
                        Belum ada riwayat agenda sebelumnya.
                    </div>
                @else
                    <div class="overflow-x-auto bg-white/90 border border-slate-200 rounded-xl shadow-sm">
                        <table class="min-w-full text-xs md:text-sm">
                            <thead class="bg-slate-100 text-slate-700">
                                <tr>
                                    <th class="px-3 py-2 md:px-4 md:py-3 text-left font-semibold">Tanggal</th>
                                    <th class="px-3 py-2 md:px-4 md:py-3 text-left font-semibold">Nama Kegiatan</th>
                                    <th class="px-3 py-2 md:px-4 md:py-3 text-left font-semibold">Tempat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($past as $kegiatan)
                                    <tr class="border-t border-slate-100 hover:bg-slate-50">
                                        <td class="px-3 py-2 md:px-4 md:py-3 whitespace-nowrap text-slate-700">
                                            {{ optional($kegiatan->tanggal)->locale('id')->isoFormat('D MMM Y') }}
                                        </td>
                                        <td class="px-3 py-2 md:px-4 md:py-3 text-slate-800">
                                            {{ $kegiatan->nama_kegiatan }}
                                        </td>
                                        <td class="px-3 py-2 md:px-4 md:py-3 text-slate-700">
                                            {{ $kegiatan->tempat }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

        </div>
    </main>

    {{-- FOOTER --}}
    <footer class="border-t border-slate-200 bg-white/90">
        <div class="max-w-6xl mx-auto px-4 py-3 text-[11px] md:text-xs text-slate-500 flex flex-col md:flex-row md:items-center md:justify-between gap-1">
            <div>
                &copy; {{ date('Y') }} — Kecamatan Watumalang.
            </div>
            <div>
                Data agenda bersumber dari sistem internal dan diperbarui secara berkala.
            </div>
        </div>
    </footer>
</div>

</body>
</html>
