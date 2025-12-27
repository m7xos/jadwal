<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Status Layanan Publik</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(180deg, #e0f2fe 0%, #f8fafc 45%, #fefce8 100%);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
<div class="min-h-screen flex flex-col">
    <header class="bg-white/95 border-b border-slate-200 shadow-sm">
        <div class="max-w-4xl mx-auto px-4 py-5">
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-sky-800">
                Status Layanan Publik
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                Cek progres layanan menggunakan kode register.
            </p>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-4xl mx-auto px-4 py-6 md:py-10">
            @if (! $layanan)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center">
                    <p class="text-lg font-semibold text-slate-700">Kode register tidak ditemukan.</p>
                    <p class="text-sm text-slate-500 mt-2">Pastikan kode yang dimasukkan benar.</p>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Kode Register</p>
                            <p class="text-xl font-bold text-slate-800">{{ $layanan->kode_register }}</p>
                        </div>
                        <div class="px-3 py-2 rounded-xl bg-sky-50 border border-sky-200">
                            <p class="text-xs uppercase tracking-wide text-sky-600">Status</p>
                            <p class="text-base font-semibold text-sky-800">{{ $layanan->status_label }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Layanan</p>
                            <p class="text-base font-semibold text-slate-800">
                                {{ $layanan->layanan?->nama ?? '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Tanggal Masuk</p>
                            <p class="text-base font-semibold text-slate-800">
                                {{ $layanan->tanggal_masuk?->format('d/m/Y') ?? '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Tanggal Selesai</p>
                            <p class="text-base font-semibold text-slate-800">
                                {{ $layanan->tanggal_selesai?->format('d/m/Y') ?? '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Pemohon</p>
                            <p class="text-base font-semibold text-slate-800">
                                {{ $layanan->nama_pemohon }}
                            </p>
                        </div>
                    </div>

                    @if ($layanan->status === \App\Models\LayananPublikRequest::STATUS_PICKED_BY_VILLAGE)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                            Berkas diambil oleh perangkat desa:
                            <strong>{{ $layanan->perangkat_desa_nama ?? '-' }}</strong>
                            ({{ $layanan->perangkat_desa_wa ?? '-' }})
                        </div>
                    @endif

                    @if ($layanan->catatan)
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                            <strong>Catatan:</strong> {{ $layanan->catatan }}
                        </div>
                    @endif
                </div>

                <div class="mt-6 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-slate-800 mb-4">Riwayat Progres</h2>
                    @if ($layanan->statusLogs->isEmpty())
                        <p class="text-sm text-slate-500">Belum ada riwayat progres.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($layanan->statusLogs as $log)
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ \App\Models\LayananPublikRequest::statusOptions()[$log->status] ?? $log->status }}
                                        </p>
                                        @if ($log->catatan)
                                            <p class="text-xs text-slate-500">{{ $log->catatan }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-400">
                                        {{ $log->created_at?->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 text-sm text-slate-500">
                    Cek status via WhatsApp: ketik
                    <span class="font-semibold text-slate-700">cek layanan {{ $layanan->kode_register }}</span>
                </div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
