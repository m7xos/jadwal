@php
    use Illuminate\Support\Facades\Storage;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Agenda Kegiatan Kantor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Tailwind CDN, biar cepat tanpa setup --}}
    <script src="https://cdn.tailwindcss.com"></script>
	{{-- Auto refresh setiap 60 detik --}}
	<!--script>
    document.addEventListener('DOMContentLoaded', () => {
        // interval dalam milidetik (contoh: 60000 = 60 detik)
        const intervalMs = 60000;

        setInterval(() => {
            // reload halaman dari server (bukan dari cache)
            window.location.reload(true);
        }, intervalMs);
    });
</script-->


    <style>
        body {
            background: radial-gradient(circle at top left, #e0f2fe, #eef2ff, #fef9c3);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">

    {{-- Header / Hero --}}
    <header class="bg-gradient-to-r from-sky-600 to-indigo-600 text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-6 md:py-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
                    Agenda Kegiatan Kantor
                </h1>
                <p class="text-sm md:text-base text-sky-100 mt-1">
                    Informasi kegiatan resmi yang dapat diakses oleh masyarakat.
                </p>
            </div>
            <div class="flex flex-col items-start md:items-end gap-1 text-sm md:text-right">
                <span class="inline-flex items-center gap-2 bg-white/10 px-3 py-1 rounded-full">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Terakhir diperbarui: {{ now()->locale('id')->isoFormat('dddd, D MMMM Y HH:mm') }}</span>
                </span>
				 <!-- Tambah ini -->
				<!--span class="text-xs text-sky-100">
					Halaman akan otomatis menyegarkan setiap 60 detik untuk menampilkan agenda terbaru.
				</span-->
                <span class="text-xs text-sky-100">
                    Halaman ini akan otomatis menampilkan agenda baru setelah diinput petugas.
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 md:py-10 space-y-10">

		{{-- AGENDA HARI INI --}}
		<section>
			<div class="flex items-center justify-between mb-4">
				<h2 class="text-xl md:text-2xl font-semibold text-slate-800 flex items-center gap-2">
					<span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-emerald-600 text-white text-sm">
						{{-- icon kalender / hari ini --}}
						<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
							 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
							<line x1="16" y1="2" x2="16" y2="6"/>
							<line x1="8" y1="2" x2="8" y2="6"/>
							<line x1="3" y1="10" x2="21" y2="10"/>
							<path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/>
						</svg>
					</span>
					Agenda Hari Ini
				</h2>
			</div>

			@if ($todayAgenda->isEmpty())
				<div class="bg-white/80 border border-dashed border-slate-300 rounded-xl p-6 text-center text-slate-500">
					Tidak ada agenda pada hari ini.
				</div>
			@else
				<div class="grid gap-4 md:gap-6 md:grid-cols-2">
					@foreach ($todayAgenda as $kegiatan)
						{{-- gunakan card yang sama seperti sebelumnya --}}
						@include('public.agenda._card', ['kegiatan' => $kegiatan, 'badge' => 'Hari ini'])
					@endforeach
				</div>
			@endif
		</section>

		{{-- AGENDA HARI BERIKUTNYA --}}
		<section class="mt-8">
			<div class="flex items-center justify-between mb-4">
				<h2 class="text-xl md:text-2xl font-semibold text-slate-800 flex items-center gap-2">
					<span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-sky-600 text-white text-sm">
						{{-- icon kalender --}}
						<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
							 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
							<line x1="16" y1="2" x2="16" y2="6"/>
							<line x1="8" y1="2" x2="8" y2="6"/>
							<line x1="3" y1="10" x2="21" y2="10"/>
						</svg>
					</span>
					Agenda Hari Berikutnya
				</h2>
			</div>

			@if ($nextAgenda->isEmpty())
				<div class="bg-white/80 border border-dashed border-slate-300 rounded-xl p-6 text-center text-slate-500">
					Belum ada agenda untuk hari-hari berikutnya.
				</div>
			@else
				<div class="grid gap-4 md:gap-6 md:grid-cols-2">
					@foreach ($nextAgenda as $kegiatan)
						{{-- card yang sama, badge "Akan datang" --}}
						@include('public.agenda._card', ['kegiatan' => $kegiatan, 'badge' => 'Akan datang'])
					@endforeach
				</div>
			@endif
		</section>


    </main>

    <footer class="border-t border-slate-200 bg-white/70 mt-8">
        <div class="max-w-6xl mx-auto px-4 py-4 text-[11px] md:text-xs text-slate-500 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                &copy; {{ date('Y') }} — Sistem Agenda Kegiatan Kantor.
            </div>
            <div class="text-slate-400">
                Data agenda dikelola oleh petugas internal. Halaman ini bersifat informasi publik.
            </div>
        </div>
    </footer>
</body>
</html>
