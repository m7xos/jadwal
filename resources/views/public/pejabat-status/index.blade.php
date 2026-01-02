<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Status Pejabat Kecamatan Watumalang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Source+Sans+3:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --ink: #0f172a;
            --muted: #475569;
            --muted-light: #94a3b8;
            --teal: #0f766e;
            --emerald: #10b981;
            --rose: #ef4444;
            --amber: #f59e0b;
            --sky: #38bdf8;
            --card: #ffffff;
            --glass: rgba(255, 255, 255, 0.82);
            --border: rgba(15, 23, 42, 0.12);
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Source Sans 3", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 8% 0%, rgba(14, 116, 144, 0.18), transparent 60%),
                radial-gradient(900px 500px at 88% 8%, rgba(234, 179, 8, 0.18), transparent 55%),
                linear-gradient(160deg, #f8fafc 0%, #fef3c7 50%, #e0f2fe 100%);
            min-height: 100vh;
        }

        h1, h2, h3 {
            font-family: "Sora", sans-serif;
        }

        .page-shell {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
        }

        .bg-orb {
            position: absolute;
            border-radius: 999px;
            filter: blur(10px);
            opacity: 0.55;
            animation: float 14s ease-in-out infinite;
            pointer-events: none;
        }

        .orb-one {
            width: 260px;
            height: 260px;
            background: rgba(16, 185, 129, 0.25);
            top: -60px;
            left: 6%;
        }

        .orb-two {
            width: 320px;
            height: 320px;
            background: rgba(56, 189, 248, 0.3);
            right: 8%;
            top: 80px;
            animation-delay: -4s;
        }

        .orb-three {
            width: 240px;
            height: 240px;
            background: rgba(251, 146, 60, 0.22);
            bottom: -60px;
            left: 20%;
            animation-delay: -6s;
        }

        .hero {
            position: relative;
            padding: 32px 0 10px;
        }

        .hero-inner {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
        }

        .kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(15, 118, 110, 0.12);
            color: var(--teal);
        }

        .hero-title h1 {
            font-size: clamp(2rem, 2.6vw, 2.6rem);
            line-height: 1.1;
            margin-top: 12px;
        }

        .hero-title p {
            margin-top: 10px;
            color: var(--muted);
            max-width: 560px;
        }

        .date-card {
            padding: 14px 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: var(--shadow);
            min-width: 220px;
        }

        .date-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--muted-light);
        }

        .date-value {
            font-size: 16px;
            margin-top: 6px;
            font-weight: 600;
        }

        .board {
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 22px;
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 0.9fr);
            gap: 18px;
        }

        .board h2 {
            font-size: 1.1rem;
        }

        .board p {
            color: var(--muted);
            margin-top: 8px;
            font-size: 0.95rem;
        }

        .board-note {
            margin-top: 12px;
            font-size: 0.8rem;
            color: var(--muted-light);
        }

        .stat-stack {
            display: grid;
            gap: 12px;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: 16px;
            border: 1px solid transparent;
            font-size: 0.9rem;
            background: #ffffff;
        }

        .stat-chip span {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .stat-chip.is-kantor {
            border-color: rgba(16, 185, 129, 0.35);
            color: #047857;
            background: rgba(16, 185, 129, 0.12);
        }

        .stat-chip.is-dinas {
            border-color: rgba(239, 68, 68, 0.35);
            color: #be123c;
            background: rgba(239, 68, 68, 0.12);
        }

        .stat-chip.is-unknown {
            border-color: rgba(100, 116, 139, 0.4);
            color: #475569;
            background: rgba(148, 163, 184, 0.18);
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr));
            gap: 20px;
            align-items: start;
        }

        .status-card {
            position: relative;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .status-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top, rgba(56, 189, 248, 0.12), transparent 55%);
            opacity: 0;
            transition: opacity 0.25s ease;
            pointer-events: none;
        }

        .status-card:hover {
            transform: translateY(-6px);
            border-color: rgba(15, 118, 110, 0.25);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.18);
        }

        .status-card:hover::after {
            opacity: 1;
        }

        .status-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 6px;
            background: var(--accent, #e2e8f0);
        }

        .status-card.is-kantor {
            --accent: linear-gradient(90deg, #34d399, #0f766e);
        }

        .status-card.is-dinas {
            --accent: linear-gradient(90deg, #fb7185, #be123c);
        }

        .status-card.is-offsite {
            --accent: linear-gradient(90deg, #fbbf24, #f97316);
        }

        .status-card.is-unknown {
            --accent: linear-gradient(90deg, #94a3b8, #64748b);
        }

        .status-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .profile {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .avatar {
            width: 62px;
            height: 62px;
            border-radius: 999px;
            border: 3px solid #ffffff;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
            position: relative;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .avatar img.is-loaded {
            opacity: 1;
        }

        .avatar-fallback {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
        }

        .status-pill {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border: 1px solid transparent;
            white-space: nowrap;
            line-height: 1.2;
            max-width: 100%;
        }

        .status-footer {
            margin-top: 10px;
            display: flex;
            justify-content: flex-start;
        }

        .status-pill.is-kantor {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border-color: rgba(16, 185, 129, 0.35);
        }

        .status-pill.is-dinas {
            background: rgba(239, 68, 68, 0.15);
            color: #be123c;
            border-color: rgba(239, 68, 68, 0.35);
        }

        .status-pill.is-offsite {
            background: rgba(245, 158, 11, 0.16);
            color: #b45309;
            border-color: rgba(245, 158, 11, 0.4);
        }

        .status-pill.is-unknown {
            background: rgba(148, 163, 184, 0.2);
            color: #475569;
            border-color: rgba(100, 116, 139, 0.35);
        }

        .jabatan {
            font-size: 1.02rem;
            font-weight: 600;
        }

        .nama {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .agenda-label {
            margin-top: 16px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .agenda-label.is-dinas {
            color: #be123c;
        }

        .agenda-list {
            margin-top: 12px;
            display: grid;
            gap: 10px;
        }

        .agenda-item {
            border-radius: 14px;
            padding: 10px 12px;
            font-size: 0.92rem;
            background: rgba(248, 250, 252, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .agenda-item.is-dinas {
            border-color: rgba(239, 68, 68, 0.25);
            background: rgba(254, 226, 226, 0.5);
        }

        .agenda-item strong {
            display: block;
            font-weight: 600;
            color: var(--ink);
        }

        .agenda-meta {
            margin-top: 4px;
            font-size: 0.78rem;
            color: var(--muted);
        }

        .empty-note {
            margin-top: 14px;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .reveal {
            opacity: 0;
            transform: translateY(14px);
            animation: rise 0.7s ease forwards;
        }

        @keyframes rise {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(16px);
            }
        }

        @media (max-width: 900px) {
            .board {
                grid-template-columns: 1fr;
            }

            .date-card {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
@php
    $countDinas = $statuses->where('status', 'Dinas Luar')->count();
    $countKantor = $statuses->where('status', 'Di Kantor')->count();
    $countUnknown = $statuses->where('status', 'Tidak diketahui')->count();
@endphp

<div class="page-shell">
    <div class="bg-orb orb-one"></div>
    <div class="bg-orb orb-two"></div>
    <div class="bg-orb orb-three"></div>

    <header class="hero">
        <div class="max-w-screen-2xl mx-auto px-4 md:px-6 hero-inner reveal" style="animation-delay: 0.05s;">
            <div class="hero-title">
                <span class="kicker">Dashboard Publik</span>
                <h1>Status Pejabat Kecamatan Watumalang</h1>
                <p>Ringkasan keberadaan pejabat berdasarkan agenda resmi hari ini.</p>
            </div>
            <div class="date-card">
                <div class="date-label">Hari ini</div>
                <div class="date-value">
                    {{ $today->locale('id')->isoFormat('dddd, D MMMM Y') }}
                </div>
            </div>
        </div>
    </header>

    <main class="pb-10">
        <div class="max-w-screen-2xl mx-auto px-4 md:px-6 space-y-6">
            {{-- Status Snapshot disembunyikan sesuai permintaan --}}

            <section class="cards-grid">
                @foreach($statuses as $item)
                    @php
                        $isDinasLuar = $item['status'] === 'Dinas Luar';
                        $isUnknown = $item['status'] === 'Tidak diketahui';
                        $isOffsite = $item['status'] === 'Tidak di Kantor';
                        $cardClass = $isUnknown
                            ? 'is-unknown'
                            : ($isDinasLuar ? 'is-dinas' : ($isOffsite ? 'is-offsite' : 'is-kantor'));
                        $pillClass = $cardClass;
                        $photoCandidates = $item['photo_candidates'] ?? [];
                        $hasPhoto = ! empty($photoCandidates);
                        $initials = '';
                        if (! empty($item['nama']) && $item['nama'] !== 'Belum terdaftar') {
                            $parts = preg_split('/\s+/', trim($item['nama']));
                            $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                        }
                        $delay = number_format($loop->index * 0.06 + 0.15, 2, '.', '');
                    @endphp

                    <article class="status-card {{ $cardClass }} reveal" style="animation-delay: {{ $delay }}s;">
                        <div class="status-header">
                            <div class="profile">
                                <div class="avatar">
                                    @if($hasPhoto)
                                        <img
                                            data-photo-candidates='@json($photoCandidates)'
                                            alt="Foto {{ $item['nama'] ?? 'Pejabat' }}"
                                            loading="lazy"
                                        />
                                    @endif
                                    <span class="avatar-fallback">
                                        {{ $initials !== '' ? $initials : 'N/A' }}
                                    </span>
                                </div>
                                <div>
                                    <div class="jabatan">{{ $item['jabatan'] }}</div>
                                    <div class="nama">{{ $item['nama'] }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="status-footer">
                            <span class="status-pill {{ $pillClass }}">
                                {{ $item['status'] }}
                            </span>
                        </div>

                        @if(($item['kegiatan'] ?? collect())->isEmpty())
                            <div class="empty-note">
                                {{ $item['status'] === 'Tidak di Kantor' ? 'Di luar jam kerja / libur.' : 'Tidak ada agenda hari ini.' }}
                            </div>
                        @elseif(($item['kegiatan_luar'] ?? collect())->isNotEmpty())
                            <div class="agenda-label is-dinas">Agenda di luar kantor</div>
                            <div class="agenda-list">
                                @foreach($item['kegiatan_luar'] as $kegiatan)
                                    <div class="agenda-item is-dinas">
                                        <strong>{{ $kegiatan->nama_kegiatan ?? '-' }}</strong>
                                        <div class="agenda-meta">
                                            {{ $kegiatan->waktu ?? '-' }} - {{ $kegiatan->tempat ?? '-' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="empty-note">
                                {{ $item['status'] === 'Tidak di Kantor'
                                    ? 'Di luar jam kerja / libur. Tidak ada agenda dinas luar hari ini.'
                                    : 'Di kantor. Tidak ada agenda dinas luar hari ini.' }}
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        </div>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('img[data-photo-candidates]').forEach((img) => {
            let candidates = [];
            try {
                candidates = JSON.parse(img.dataset.photoCandidates || '[]');
            } catch (error) {
                candidates = [];
            }

            if (!Array.isArray(candidates) || candidates.length === 0) {
                return;
            }

            let index = 0;
            const loadNext = () => {
                if (index >= candidates.length) {
                    return;
                }
                const url = candidates[index];
                index += 1;
                const tester = new Image();
                tester.onload = () => {
                    img.src = url;
                    img.classList.add('is-loaded');
                };
                tester.onerror = loadNext;
                tester.src = url;
            };

            loadNext();
        });

        const pauseAutoScroll = (state, ms = 3000) => {
            state.pausedUntil = performance.now() + ms;
            state.lastTime = null;
        };

        const autoScrollState = {
            direction: 1,
            lastTime: null,
            pausedUntil: 0,
            speed: 0.45,
        };

        const autoScrollStep = (time) => {
            const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
            if (maxScroll <= 4) {
                requestAnimationFrame(autoScrollStep);
                return;
            }
            if (time < autoScrollState.pausedUntil) {
                requestAnimationFrame(autoScrollStep);
                return;
            }
            if (autoScrollState.lastTime === null) {
                autoScrollState.lastTime = time;
            }

            const delta = time - autoScrollState.lastTime;
            autoScrollState.lastTime = time;
            const current = window.scrollY || document.documentElement.scrollTop;
            let next = current + autoScrollState.direction * autoScrollState.speed * delta;

            if (next >= maxScroll) {
                next = maxScroll;
                autoScrollState.direction = -1;
                pauseAutoScroll(autoScrollState, 1200);
            } else if (next <= 0) {
                next = 0;
                autoScrollState.direction = 1;
                pauseAutoScroll(autoScrollState, 1200);
            }

            window.scrollTo(0, next);
            requestAnimationFrame(autoScrollStep);
        };

        window.addEventListener('wheel', () => pauseAutoScroll(autoScrollState), { passive: true });
        window.addEventListener('touchstart', () => pauseAutoScroll(autoScrollState), { passive: true });
        window.addEventListener('keydown', () => pauseAutoScroll(autoScrollState), { passive: true });

        requestAnimationFrame(autoScrollStep);
    });
</script>
</body>
</html>
