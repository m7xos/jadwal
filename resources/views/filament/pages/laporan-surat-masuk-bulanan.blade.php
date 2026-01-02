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

        /* Khusus judul kop: font 14 bold */
        .kop-title {
            font-size: 14px !important;
            font-weight: bold !important;
        }

        /* Hilangkan warna background (layar & print) */
        body,
        .fi-main,
        .print-area {
            background: transparent !important;
        }
        .fi-page-header-main-ctn,
        .fi-page-header,
        .fi-page-main,
        .fi-page-content {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .ttd-block {
            line-height: 1.2;
        }
        .ttd-spacer {
            height: 18mm;
        }
        .ttd-name {
            font-weight: 600;
            text-decoration: underline;
            text-transform: uppercase;
            margin: 0;
        }
        .ttd-nip {
            margin-top: 0;
        }

        /* Garis kop dari pojok ke pojok */
        .kop-garis {
            width: 100%;
            border-bottom: 2px solid #000000;
            margin-top: 2px;
        }

        /* TABEL LAPORAN: garis hitam jelas & responsif */
        .laporan-wrapper {
            margin-bottom: 8mm;
        }

        .kop-wrapper {
            margin: 0 0 2mm 0 !important;
            padding: 0 !important;
        }

        .judul-blok {
            margin: 0 0 2mm 0 !important;
            padding: 0 !important;
        }

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
                /* Folio 8.5 x 13 inch, orientasi landscape: 330 x 215 mm */
                size: 330mm 215mm;
                margin: 4mm 10mm 12mm 10mm;
            }

            body {
                counter-reset: page 1;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* SEMUA elemen disembunyikan dulu */
            body * {
                visibility: hidden;
            }

            /* Hanya .print-area yang boleh tampak & tercetak */
            .print-area,
            .print-area * {
                visibility: visible;
                color: #000000 !important;
            }

            /* Pastikan .print-area mengisi halaman cetak */
            /* Reset padding/margin layout Filament agar konten naik ke atas */
            .fi-main,
            .fi-body,
            .fi-page {
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-area {
                position: relative;
                width: 100%;
                margin: 0;
                box-shadow: none !important;
                border: none !important;
                background: transparent !important; /* tanpa warna saat print */
                padding: 0 0 60mm 0 !important; /* ruang bawah untuk footer */
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
            .print-area .kop-wrapper {
                margin-top: -2mm !important;
                margin-bottom: 0.5mm !important;
            }
            .print-area .judul-blok {
                margin-top: 0 !important;
                margin-bottom: 0.5mm !important;
            }
			
			/* Bar filter bulan + tombol cetak */
			.filter-bar {
				display: flex;
				justify-content: center;
				margin-bottom: 1rem;
			}

        }
    </style>

{{-- FILTER & TOMBOL CETAK (TIDAK IKUT TERCETAK) --}}
<div class="no-print mb-4" style="text-align: center;">
    <div style="display: inline-flex; align-items: flex-end; gap: 10px;">
        <div style="display: flex; flex-direction: column; align-items: flex-start;">
            <label for="jenis_rekap" style="font-size: 11px; color: #4b5563; margin-bottom: 2px;">
                Jenis rekap
            </label>
            <select
                id="jenis_rekap"
                wire:model.live="jenisRekap"
                style="width: 160px; height: 32px; font-size: 12px; padding: 2px 6px;
                       border-radius: 0.5rem; border: 1px solid #d1d5db;"
                class="focus:border-primary-500 focus:ring-primary-500"
            >
                <option value="bulanan">Bulanan</option>
                <option value="tahunan">Tahunan</option>
            </select>
        </div>

        @if(($jenisRekap ?? 'bulanan') === 'tahunan')
            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                <label for="tahun" style="font-size: 11px; color: #4b5563; margin-bottom: 2px;">
                    Tahun rekap
                </label>
                <input
                    id="tahun"
                    type="number"
                    min="2000"
                    max="2100"
                    wire:model.live="tahun"
                    style="width: 120px; height: 32px; font-size: 12px; padding: 2px 6px;
                           border-radius: 0.5rem; border: 1px solid #d1d5db;"
                    class="focus:border-primary-500 focus:ring-primary-500"
                />
            </div>
        @else
            <div style="display: flex; flex-direction: column; align-items: flex-start;">
                <label for="bulan" style="font-size: 11px; color: #4b5563; margin-bottom: 2px;">
                    Bulan rekap
                </label>
                <input
                    id="bulan"
                    type="month"
                    wire:model.live="bulan"
                    style="width: 180px; height: 32px; font-size: 12px; padding: 2px 6px;
                           border-radius: 0.5rem; border: 1px solid #d1d5db;"
                    class="focus:border-primary-500 focus:ring-primary-500"
                />
            </div>
        @endif

        <button
            type="button"
            onclick="window.print()"
            style="display: inline-flex; align-items: center; gap: 6px;
                   border-radius: 0.5rem; border: 1px solid #1d4ed8;
                   background-color: #1d4ed8; padding: 6px 14px;
                   font-size: 12px; font-weight: 600; color: #ffffff;
                   box-shadow: 0 2px 4px rgba(0,0,0,0.2); cursor: pointer;"
            class="hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
        >
            {{-- Icon print (SVG) --}}
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
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
        <div class="kop-wrapper">
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
                        <div class="kop-title uppercase">
                            PEMERINTAH KABUPATEN WONOSOBO
                        </div>
                        <div class="kop-title uppercase">
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
        </div>

        {{-- TABEL LAPORAN --}}
        <div class="overflow-x-auto mt-2 laporan-wrapper">
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
                            Personil yang Hadir
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
                                Tidak ada data kegiatan pada periode ini.
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

        <div class="mt-8 flex justify-end ttd-block" style="text-align: center;">
            <div class="w-72 text-center">
                <div>
                    <br>
                    Wonosobo,
                    {{ now()->locale('id')->isoFormat('D MMMM Y') }}
                </div>
                <div class="mt-1">
                    Camat Watumalang
                </div>
                <div class="ttd-spacer"></div>
                <div class="ttd-name">
                    {{ $camat->nama ?? $namaCamat ?? '____________________' }}
                </div>

                @if(! empty($camat?->nip))
                    <div class="ttd-nip">
                        NIP. {{ $camat->nip }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>
