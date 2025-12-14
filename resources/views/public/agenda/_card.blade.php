@props(['kegiatan'])

<div class="bg-slate-900/80 border border-slate-700 rounded-xl p-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1">
        <div class="text-sm font-medium uppercase tracking-wide text-sky-400">
            {{ optional($kegiatan->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}
        </div>
        <div class="text-xs text-slate-300">
            Nomor: <span class="font-mono">{{ $kegiatan->nomor ?? '-' }}</span>
        </div>
    </div>

    <h3 class="mt-1 text-lg font-semibold text-slate-50">
        {{ $kegiatan->nama_kegiatan }}
    </h3>

    <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
        <div>
            <div class="text-slate-400 text-xs">Waktu</div>
            <div class="font-medium">{{ $kegiatan->waktu ?? '-' }}</div>
        </div>
        <div>
            <div class="text-slate-400 text-xs">Tempat</div>
            <div class="font-medium">{{ $kegiatan->tempat ?? '-' }}</div>
        </div>
        <div>
            <div class="text-slate-400 text-xs">Status</div>
            <div class="font-medium">
                @if(($kegiatan->sudah_disposisi ?? false) === true)
                    <span class="text-emerald-400">Sudah disposisi</span>
                @else
                    <span class="text-amber-300">Menunggu disposisi</span>
                @endif
            </div>
        </div>
    </div>

    @if($kegiatan->personils && $kegiatan->personils->isNotEmpty())
        <div class="mt-3 text-sm">
            <div class="text-slate-400 text-xs mb-1">Personil yang menghadiri</div>
            <ul class="list-disc list-inside space-y-0.5">
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
            <div class="text-slate-400 text-xs mb-1">Keterangan</div>
            <div class="text-slate-200">
                {{ $kegiatan->keterangan }}
            </div>
        </div>
    @endif

    @php
        $previewUrl = $kegiatan->surat_preview_url;
    @endphp

    @if($previewUrl)
        <div class="mt-3 text-xs">
            <a href="{{ $previewUrl }}"
               target="_blank"
               class="inline-flex items-center px-3 py-1.5 rounded-full bg-sky-600 hover:bg-sky-500 text-white">
                📎 Lihat Surat Undangan (PDF)
            </a>
        </div>
    @endif
</div>
