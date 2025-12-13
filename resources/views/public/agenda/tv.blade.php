<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Agenda Kecamatan Watumalang Hari Ini - Layar TV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000000; /* disamakan dengan background animasi */
            color: #f9fafb;
        }

        @keyframes move {
            100% {
                transform: translate3d(0, 0, 1px) rotate(360deg);
            }
        }

        .background {
            position: fixed;
            width: 100vw;
            height: 100vh;
            top: 0;
            left: 0;
            background: #000000;
            overflow: hidden;
            z-index: 0;
        }

        .background span {
            width: 13vmin;
            height: 13vmin;
            border-radius: 13vmin;
            backface-visibility: hidden;
            position: absolute;
            animation: move;
            animation-duration: 45s;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        .background span:nth-child(0) {
            color: #583C87;
            top: 84%;
            left: 48%;
            animation-duration: 54s;
            animation-delay: -27s;
            transform-origin: -20vw 15vh;
            box-shadow: -26vmin 0 4.214145441092953vmin currentColor;
        }
        .background span:nth-child(1) {
            color: #583C87;
            top: 45%;
            left: 57%;
            animation-duration: 10s;
            animation-delay: -34s;
            transform-origin: 2vw 14vh;
            box-shadow: -26vmin 0 3.926940546995974vmin currentColor;
        }
        .background span:nth-child(2) {
            color: #583C87;
            top: 79%;
            left: 6%;
            animation-duration: 21s;
            animation-delay: -37s;
            transform-origin: -21vw 25vh;
            box-shadow: 26vmin 0 3.90050998880299vmin currentColor;
        }
        .background span:nth-child(3) {
            color: #4e3b41;
            top: 5%;
            left: 76%;
            animation-duration: 11s;
            animation-delay: -46s;
            transform-origin: -13vw -22vh;
            box-shadow: 26vmin 0 3.8989811621826416vmin currentColor;
        }
        .background span:nth-child(4) {
            color: #FFACAC;
            top: 87%;
            left: 67%;
            animation-duration: 39s;
            animation-delay: -16s;
            transform-origin: -13vw -16vh;
            box-shadow: -26vmin 0 3.9957408500895304vmin currentColor;
        }
        .background span:nth-child(5) {
            color: #4e3b41;
            top: 59%;
            left: 36%;
            animation-duration: 26s;
            animation-delay: -19s;
            transform-origin: -13vw -18vh;
            box-shadow: 26vmin 0 3.6026678388668336vmin currentColor;
        }
        .background span:nth-child(6) {
            color: #583C87;
            top: 40%;
            left: 90%;
            animation-duration: 42s;
            animation-delay: -14s;
            transform-origin: 5vw -15vh;
            box-shadow: 26vmin 0 4.089637091663482vmin currentColor;
        }
        .background span:nth-child(7) {
            color: #583C87;
            top: 77%;
            left: 30%;
            animation-duration: 42s;
            animation-delay: -3s;
            transform-origin: -6vw -15vh;
            box-shadow: -26vmin 0 4.148390602178375vmin currentColor;
        }
        .background span:nth-child(8) {
            color: #FFACAC;
            top: 63%;
            left: 33%;
            animation-duration: 42s;
            animation-delay: -3s;
            transform-origin: 20vw 8vh;
            box-shadow: -26vmin 0 3.287326730611522vmin currentColor;
        }
        .background span:nth-child(9) {
            color: #583C87;
            top: 93%;
            left: 63%;
            animation-duration: 35s;
            animation-delay: -34s;
            transform-origin: -17vw 11vh;
            box-shadow: 26vmin 0 4.138857418610817vmin currentColor;
        }
        .background span:nth-child(10) {
            color: #FFACAC;
            top: 74%;
            left: 50%;
            animation-duration: 26s;
            animation-delay: -47s;
            transform-origin: 11vw 17vh;
            box-shadow: -26vmin 0 3.716559183784282vmin currentColor;
        }
        .background span:nth-child(11) {
            color: #4e3b41;
            top: 61%;
            left: 100%;
            animation-duration: 47s;
            animation-delay: -7s;
            transform-origin: 12vw -3vh;
            box-shadow: 26vmin 0 3.752607266892412vmin currentColor;
        }
        .background span:nth-child(12) {
            color: #583C87;
            top: 26%;
            left: 100%;
            animation-duration: 49s;
            animation-delay: -50s;
            transform-origin: -2vw 11vh;
            box-shadow: 26vmin 0 3.639576274387289vmin currentColor;
        }
        .background span:nth-child(13) {
            color: #FFACAC;
            top: 63%;
            left: 91%;
            animation-duration: 41s;
            animation-delay: -49s;
            transform-origin: 6vw -1vh;
            box-shadow: -26vmin 0 4.2115501248679vmin currentColor;
        }
        .background span:nth-child(14) {
            color: #FFACAC;
            top: 62%;
            left: 33%;
            animation-duration: 51s;
            animation-delay: -28s;
            transform-origin: 3vw -21vh;
            box-shadow: 26vmin 0 3.8273636171831056vmin currentColor;
        }
        .background span:nth-child(15) {
            color: #583C87;
            top: 25%;
            left: 84%;
            animation-duration: 42s;
            animation-delay: -43s;
            transform-origin: -10vw 24vh;
            box-shadow: -26vmin 0 3.7705183300201055vmin currentColor;
        }
        .background span:nth-child(16) {
            color: #583C87;
            top: 37%;
            left: 11%;
            animation-duration: 23s;
            animation-delay: -24s;
            transform-origin: 16vw 17vh;
            box-shadow: -26vmin 0 4.1546936015616005vmin currentColor;
        }
        .background span:nth-child(17) {
            color: #4e3b41;
            top: 10%;
            left: 76%;
            animation-duration: 25s;
            animation-delay: -43s;
            transform-origin: 17vw -5vh;
            box-shadow: 26vmin 0 3.2506295083898213vmin currentColor;
        }
        .background span:nth-child(18) {
            color: #583C87;
            top: 39%;
            left: 68%;
            animation-duration: 11s;
            animation-delay: -1s;
            transform-origin: -20vw -14vh;
            box-shadow: 26vmin 0 3.890332758825883vmin currentColor;
        }
        .background span:nth-child(19) {
            color: #583C87;
            top: 38%;
            left: 85%;
            animation-duration: 26s;
            animation-delay: -23s;
            transform-origin: -4vw 17vh;
            box-shadow: 26vmin 0 3.872388619612956vmin currentColor;
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
<body class="min-h-screen">

    {{-- Background animasi --}}
    <div class="background">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>

    {{-- Konten utama di atas background --}}
    <div class="relative z-10 max-w-6xl mx-auto px-4 md:px-6 py-6 flex flex-col gap-4 text-slate-50">
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
                    <div class="rounded-2xl border border-sky-700/40 bg-sky-900/60 px-4 md:px-5 py-4 flex flex-col md:flex-row gap-4 items-start shadow-lg backdrop-blur-sm">
                        {{-- waktu besar di kiri --}} 
                        <div class="md:w-40 w-full md:text-right text-left md:pr-4 md:border-r border-b md:border-b-0 border-sky-700/60 md:pb-0 pb-3 shrink-0">
                            <div class="text-lg md:text-xl font-semibold text-sky-200">
                                {{ $kegiatan->waktu }}
                            </div>
                            <div class="text-xs md:text-sm text-sky-300 mt-1">
                                {{ optional($kegiatan->tanggal)->locale('id')->isoFormat('D MMM Y') }}
                            </div>
                            <div class="text-[11px] text-sky-400 mt-2">
                                No: {{ $kegiatan->nomor ?? '-' }}
                            </div>
                        </div>

                        {{-- isi utama --}} 
                        <div class="flex-1 min-w-0">
                            <div class="text-xl md:text-2xl font-bold mb-1 break-words">
                                {{ $kegiatan->nama_kegiatan }}
                            </div>
                            <div class="text-base md:text-lg text-sky-100 mb-2 break-words">
                                📍 {{ $kegiatan->tempat }}
                            </div>

                            @php
                                $personils = $kegiatan->personils ?? collect();
                            @endphp

                            @if ($personils->isNotEmpty())
                                <div class="text-sm md:text-base text-sky-100 break-words">
                                    👥
                                    @foreach ($personils as $idx => $p)
                                        {{ $idx ? ' • ' : '' }}{{ $p->nama }}@if($p->jabatan) ({{ $p->jabatan }})@endif
                                    @endforeach
                                </div>
                            @endif

                            @if ($kegiatan->keterangan)
                                <div class="text-sm md:text-base text-slate-200 mt-2 break-words">
                                    {{ $kegiatan->keterangan }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </main>

        <x-app-footer class="mt-4" text-class="text-slate-300" />
    </div>

</body>
</html>
