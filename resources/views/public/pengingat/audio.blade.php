<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengingat Apel & Presensi Otomatis</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Tailwind CDN hanya untuk tampilan, bisa dihapus kalau tidak perlu --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: radial-gradient(circle at top left, #0f172a, #020617);
        }
    </style>
</head>
<body class="min-h-screen text-slate-50 flex flex-col items-center justify-center">

<div class="max-w-xl w-full px-4">
    <div class="bg-slate-900/70 border border-slate-700 rounded-2xl p-6 shadow-xl">
        <h1 class="text-2xl font-bold mb-2 text-sky-300">
            Pengingat Apel & Presensi Otomatis
        </h1>
        <p class="text-sm text-slate-300 mb-4">
            Halaman ini akan memutar audio pengingat secara otomatis sesuai jadwal berikut
            (berdasarkan jam lokal komputer ini):
        </p>

        <ul class="text-sm text-slate-200 space-y-1 mb-4">
            <li>• <span class="font-semibold">Senin – Kamis, 07.30 WIB</span> → Pengingat Apel</li>
            <li>• <span class="font-semibold">Senin – Kamis, 16.00 WIB</span> → Pengingat Presensi</li>
            <li>• <span class="font-semibold">Jumat, 11.00 WIB</span> → Pengingat Presensi</li>
        </ul>

        <div class="text-xs text-slate-400 mb-4">
            Pastikan:
            <ul class="list-disc pl-5 mt-1 space-y-1">
                <li>Laptop/PC ini tersambung ke TV/monitor dan speaker.</li>
                <li>Volume tidak dimute.</li>
                <li>Halaman ini dibiarkan terbuka (jangan ditutup).</li>
            </ul>
        </div>

        <div class="bg-slate-800/70 rounded-xl px-3 py-2 mb-4 text-xs">
            <div>Jam saat ini (menurut komputer):</div>
            <div id="clock" class="font-mono text-lg text-emerald-300 mt-1"></div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs">
            <button
                type="button"
                onclick="playTest('apel')"
                class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-medium"
            >
                Tes Suara Apel
            </button>
            <button
                type="button"
                onclick="playTest('presensi')"
                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-medium"
            >
                Tes Suara Presensi
            </button>
        </div>

        <div id="last-play-info" class="mt-4 text-[11px] text-slate-400">
            Audio belum pernah diputar hari ini.
        </div>
    </div>
</div>

{{-- Audio file --}}
<audio id="audio-apel" src="{{ asset('audio/apel.mp3') }}"></audio>
<audio id="audio-presensi" src="{{ asset('audio/presensi.mp3') }}"></audio>

<script>
    // Jadwal:
    // Senin–Kamis (1–4): 07:30 apel, 16:00 presensi
    // Jumat (5): 11:00 presensi

    const schedules = [
        { days: [1, 2, 3, 4], hour: 7,  minute: 30, type: 'apel' },
        { days: [1, 2, 3, 4], hour: 16, minute: 0,  type: 'presensi' },
        { days: [5],          hour: 11, minute: 0,  type: 'presensi' },
    ];

    function getDayName(dayIndex) {
        const names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return names[dayIndex] || '';
    }

    function pad2(num) {
        return num.toString().padStart(2, '0');
    }

    function updateClock() {
        const now = new Date();
        const clockEl = document.getElementById('clock');

        const dayName = getDayName(now.getDay());
        const dateStr = `${pad2(now.getDate())}-${pad2(now.getMonth() + 1)}-${now.getFullYear()}`;
        const timeStr = `${pad2(now.getHours())}:${pad2(now.getMinutes())}:${pad2(now.getSeconds())}`;

        clockEl.textContent = `${dayName}, ${dateStr}  ${timeStr}`;
    }

    function getTodayKey() {
        const now = new Date();
        const y = now.getFullYear();
        const m = pad2(now.getMonth() + 1);
        const d = pad2(now.getDate());
        return `${y}-${m}-${d}`;
    }

    function getPlayedKey(type, hour, minute) {
        const dayKey = getTodayKey();
        return `pengingat-played-${dayKey}-${type}-${pad2(hour)}${pad2(minute)}`;
    }

    function markPlayed(type, hour, minute) {
        const key = getPlayedKey(type, hour, minute);
        localStorage.setItem(key, '1');

        const info = document.getElementById('last-play-info');
        const now = new Date();
        const timeStr = `${pad2(now.getHours())}:${pad2(now.getMinutes())}:${pad2(now.getSeconds())}`;
        info.textContent = `Terakhir memutar audio (${type}) pada ${getDayName(now.getDay())}, ${getTodayKey()} ${timeStr}`;
    }

    function hasPlayed(type, hour, minute) {
        const key = getPlayedKey(type, hour, minute);
        return localStorage.getItem(key) === '1';
    }

    function playAudio(type) {
        let audioEl = null;
        let label = '';

        if (type === 'apel') {
            audioEl = document.getElementById('audio-apel');
            label = 'Apel Pagi';
        } else if (type === 'presensi') {
            audioEl = document.getElementById('audio-presensi');
            label = 'Pengingat Presensi';
        }

        if (!audioEl) {
            console.warn('Audio element not found for type:', type);
            return;
        }

        // Set ulang ke awal lalu play
        audioEl.currentTime = 0;
        const playPromise = audioEl.play();

        if (playPromise !== undefined) {
            playPromise.then(() => {
                console.log('Memutar audio:', label);
            }).catch(error => {
                console.error('Gagal memutar audio. Browser mungkin butuh interaksi pengguna terlebih dahulu.', error);
            });
        }
    }

    function checkSchedule() {
        const now = new Date();
        const day = now.getDay();      // 0=Sunday, 1=Monday, ..., 6=Saturday
        const hour = now.getHours();
        const minute = now.getMinutes();
        const second = now.getSeconds();

        schedules.forEach(schedule => {
            if (!schedule.days.includes(day)) {
                return;
            }

            if (hour === schedule.hour && minute === schedule.minute) {
                // Untuk menghindari audio diputar berkali-kali dalam 1 menit,
                // kita hanya izinkan jika:
                // - belum pernah diputar hari ini untuk jadwal tersebut, dan
                // - detik masih di awal (misal < 10 detik pertama)
                if (!hasPlayed(schedule.type, schedule.hour, schedule.minute) && second < 10) {
                    playAudio(schedule.type);
                    markPlayed(schedule.type, schedule.hour, schedule.minute);
                }
            }
        });
    }

    function playTest(type) {
        playAudio(type);
    }

    // Update jam setiap detik
    setInterval(updateClock, 1000);
    updateClock();

    // Cek jadwal setiap 5 detik
    setInterval(checkSchedule, 5000);
</script>

</body>
</html>
