{{-- resources/views/filament/pages/laporan-surat-masuk-bulanan.blade.php --}}
<x-filament-panels::page>
    <style>
        /* Hilangkan sidebar & topbar Filament di halaman ini (untuk tampilan biasa) */
        .fi-layout .fi-sidebar,
        .fi-layout .fi-topbar {
            display: none !important;
        }

        .fi-layout .fi-main {
            margin-inline-start: 0 !important;
        }

        /* Standarisasi font ke Arial 12 */
        body,
        .fi-main,
        .print-area,
        .print-area * {
            font-family: Arial, sans-serif !important;
            font-size: 12px !important;
        }

        /* Hilangkan warna background (layar & print) */
        body,
        .fi-main,
        .print-area {
            background: transparent !important;
        }

        /* Garis kop dari pojok ke pojok */
        .kop-garis {
            width: 100%;
            border-bottom: 2px solid #000000;
            margin-top: 4px;
        }

        /* TABEL LAPORAN: garis hitam jelas & responsif */
        table.laporan {
            border-collapse: collapse;
            width: 100%;
            table-layout: auto;
        }

        table.laporan th,
        table.laporan td {
            border: 1px solid #000000;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
            white-space: normal;
        }

        /* Kolom sempit untuk No & Paraf, lainnya menyesuaikan data */
        table.laporan th:nth-child(1),
        table.laporan td:nth-child(1) {
            width: 4%;
            text-align: center;
        }

        table.laporan th:nth-child(9),
        table.laporan td:nth-child(9) {
            width: 8%;
            text-align: center;
        }

        table.laporan th:nth-child(2),
        table.laporan td:nth-child(2) {
            width: 14%;
        }

        table.laporan th:nth-child(3),
        table.laporan td:nth-child(3),
        table.laporan th:nth-child(4),
        table.laporan td:nth-child(4) {
            width: 10%;
            text-align: center;
        }

        /* Judul & kop tetap rata tengah */
        .kop-judul {
            text-align: center;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            /* SEMUA elemen disembunyikan dulu */
            body * {
                visibility: hidden;
            }

            /* Hanya .print-area yang boleh tampak & tercetak */
            .print-area,
            .print-area * {
                visibility: visible;
            }

            /* Pastikan .print-area mengisi halaman cetak */
            .print-area {
                position: absolute;
                inset: 0;       /* top:0; right:0; bottom:0; left:0; */
                margin: 0;
                box-shadow: none !important;
                border: none !important;
                background: transparent !important; /* tanpa warna saat print */
            }

            /* Elemen yang memang tidak perlu tercetak (filter, tombol, dsb) */
            .no-print {
                display: none !important;
            }

            /* Pastikan garis tabel & garis kop tetap hitam saat di-print */
            table.laporan th,
            table.laporan td {
                border: 1px solid #000000 !important;
                background: transparent !important; /* tidak ada shading warna */
            }

            .kop-garis {
                border-bottom: 2px solid #000000 !important;
            }
        }
    </style>

    {{-- FILTER & TOMBOL CETAK (TIDAK IKUT TERCETAK) --}}
    <div class="no-print mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="flex flex-col gap-2 md:flex-row md:items-end" align="center">
            <div>
                <label for="bulan" class="block text-xs font-s text-gray-600 mb-1" >
                    Bulan rekap
                </label>
                <input 
                    id="bulan"
                    type="month"
                    wire:model.live="bulan"
                    class="fi-input block w-32 rounded-lg border-gray-300 text-sm shadow-sm
                           focus:border-primary-500 focus:ring-primary-500"
                />
            </div>
			
            <button
                type="button"
                onclick="window.print()"
                class="mt-3 inline-flex items-center gap-2 rounded-lg border border-primary-700 bg-primary-700
                       px-5 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-primary-800
                       focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 md:mt-0"
            >
                {{-- Icon print (SVG) --}}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M6 9V4h12v5M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0v3h12v-3M9 13h6" />
                </svg>
                <span>Cetak Laporan</span>
            </button>
        </div>
    </div>

    {{-- AREA CETAK --}}
    <div class="print-area p-4 md:p-6">

        {{-- JUDUL / KOP + LOGO --}}
        <div class="mb-2">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    {{-- Logo pojok kiri atas --}}
                    <td style="width: 90px; text-align: left; vertical-align: top;">
                        <img
                            src="{{ asset('images/logo-wonosobo.png') }}"
                            alt="Logo Kabupaten Wonosobo"
                            style="height: 50px; width: auto;"
                        >
                    </td>

                    {{-- Teks kop rata tengah --}}
                    <td class="kop-judul" style="vertical-align: top;">
                        <div class="font-semibold uppercase">
                            PEMERINTAH KABUPATEN WONOSOBO
                        </div>
                        <div class="font-semibold uppercase">
                            KECAMATAN WATUMALANG
                        </div>
                        <div class="mt-1">
                            Alamat: Jalan Kyai Jebeng Lintang Nomor 29 Kelurahan Wonoroto Kecamatan Watumalang
                            Kabupaten Wonosobo, 56352
                        </div>
                    </td>
                </tr>
            </table>

            {{-- GARIS DARI POJOK KIRI KE POJOK KANAN, DI BAWAH LOGO + TEKS --}}
            <div class="kop-garis"></div>
            <br>
        </div>

        <div class="mb-3 kop-judul">
            <div class="font-bold uppercase">
                LAPORAN REKAP SURAT MASUK
            </div>
            <br>
            @if($bulanLabel)
                <div class="mt-1">
                    Bulan: <span class="font-semibold">{{ $bulanLabel }}</span>
                </div>
            @endif
        </div>

        {{-- TABEL LAPORAN --}}
        <div class="overflow-x-auto mt-2">
            <table class="laporan">
                <thead>
                    <tr>
                        <th class="text-center align-middle">No</th>
                        <th class="text-center align-middle">Nomor Surat</th>
                        <th class="text-center align-middle">Tanggal Surat</th>
                        <th class="text-center align-middle">Waktu</th>
                        <th class="text-center align-middle">Nama Kegiatan</th>
                        <th class="text-center align-middle">Tempat</th>
                        <th class="text-center align-middle">Keterangan</th>
                        <th class="text-center align-middle">
                            Personil yang mendapat disposisi
                        </th>
                        <th class="text-center align-middle">Paraf</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="text-center align-top">
                                {{ $row['no'] ?? '' }}
                            </td>
                            <td class="align-top">
                                {{ $row['nomor'] ?? '' }}
                            </td>
                            <td class="text-center align-top">
                                {{ $row['tanggal_surat'] ?? '' }}
                            </td>
                            <td class="text-center align-top">
                                {{ $row['waktu'] ?? '' }}
                            </td>
                            <td class="align-top">
                                {{ $row['nama_kegiatan'] ?? '' }}
                            </td>
                            <td class="align-top">
                                {{ $row['tempat'] ?? '' }}
                            </td>
                            <td class="align-top">
                                {{ $row['keterangan'] ?? '' }}
                            </td>
                            <td class="align-top">
                                {{ $row['personil_disp'] ?? '' }}
                            </td>
                            <td class="align-top text-center">
                                {{-- kolom paraf kosong untuk paraf manual --}}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 text-center">
                                Tidak ada data surat masuk pada bulan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- BLOK TTD CAMAT (KANAN BAWAH) --}}
        @php
            $camat = \App\Models\Personil::where('jabatan', 'like', '%Camat Watumalang%')->first();
        @endphp

        <div class="mt-8 flex justify-end" style="text-align: center;">
            <div class="w-72 text-center">
                <div>
                    <br>
                    Wonosobo,
                    {{ now()->locale('id')->isoFormat('D MMMM Y') }}
                </div>
                <div class="mt-1">
                    Camat Watumalang
                </div>
                <br></br>
				<br></br>
                <div class="mt-10 font-semibold underline uppercase">
                    {{ $camat->nama ?? $namaCamat ?? '____________________' }}
                </div>

                @if(! empty($camat?->nip))
                    <div class="mt-1">
                        NIP. {{ $camat->nip }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
