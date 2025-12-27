<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pendaftaran Layanan Publik</title>
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
                Pendaftaran Layanan Publik
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                Isi data singkat untuk mendapatkan kode register dan nomor antrian.
            </p>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-4xl mx-auto px-4 py-6 md:py-10 space-y-6">
            @if (session('register_success'))
                @php($info = session('register_success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-5">
                    <p class="text-lg font-semibold">Pendaftaran berhasil.</p>
                    <p class="text-sm mt-2">
                        Kode Register: <strong>{{ $info['kode_register'] }}</strong><br>
                        No Antrian: <strong>{{ $info['queue_number'] ?? '-' }}</strong><br>
                        Layanan: <strong>{{ $info['layanan'] ?? '-' }}</strong>
                    </p>
                    <p class="text-xs text-emerald-700 mt-3">
                        Simpan kode ini untuk cek status layanan.
                        <a class="underline" href="{{ url('/layanan/status/' . $info['kode_register']) }}">Cek status sekarang</a>
                    </p>
                </div>
            @endif

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <form method="POST" action="{{ route('public.layanan.register.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nama</label>
                        <input
                            type="text"
                            name="nama_pemohon"
                            value="{{ old('nama_pemohon') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                            required
                        />
                        @error('nama_pemohon')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nomor HP/WA</label>
                        <input
                            type="text"
                            name="no_wa_pemohon"
                            value="{{ old('no_wa_pemohon') }}"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                            placeholder="08xxx atau 62xxx"
                            required
                        />
                        @error('no_wa_pemohon')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Jenis Layanan</label>
                        <select
                            name="layanan_publik_id"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400"
                            required
                        >
                            <option value="">Pilih layanan</option>
                            @foreach ($layanan as $item)
                                <option value="{{ $item->id }}" @selected(old('layanan_publik_id') == $item->id)>
                                    {{ $item->nama }}{{ $item->kategori ? ' (' . $item->kategori . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('layanan_publik_id')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full md:w-auto px-4 py-2 rounded-lg bg-sky-600 text-white font-semibold hover:bg-sky-500"
                    >
                        Daftar Layanan
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>
