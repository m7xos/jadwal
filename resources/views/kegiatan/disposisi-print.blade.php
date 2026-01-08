<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Disposisi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            size: 216mm 330mm;
            margin: 4mm 8mm 6mm;
        }
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }
        .no-print {
            margin: 12px 0;
        }
        .sheet {
            border: 1px solid #111827;
            padding: 10px 12px 12px;
            box-sizing: border-box;
            height: 160mm;
            break-inside: avoid;
            page-break-inside: avoid;
            font-size: 13pt;
        }
        .sheet + .sheet {
            margin-top: 4mm;
        }
        .header {
            display: grid;
            grid-template-columns: 90px 1fr;
            gap: 12px;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
        }
        .header-logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .header-logo img {
            width: 70px;
            height: auto;
        }
        .header-text {
            text-align: center;
        }
        .header .line-1 {
            font-weight: 700;
            letter-spacing: 0.5px;
            font-size: 13pt;
        }
        .header .line-2 {
            font-weight: 700;
            margin-top: 2px;
            font-size: 13pt;
        }
        .header .line-3 {
            font-size: 11px;
            margin-top: 4px;
        }
        .header .line-4,
        .header .line-5 {
            font-size: 11px;
            margin-top: 2px;
        }
        .title {
            text-align: center;
            font-size: 13pt;
            font-weight: 700;
            margin: 8px 0 6px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            vertical-align: top;
            padding: 2px 4px;
        }
        .label {
            width: 30%;
        }
        .right-label {
            width: 28%;
        }
        .line {
            display: inline-block;
            min-width: 160px;
            border-bottom: 1px dotted #111827;
            padding-bottom: 2px;
        }
        .section-title {
            font-weight: 700;
            margin: 4px 0;
        }
        .checklist {
            margin-top: 4px;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 1px;
        }
        .checkbox {
            width: 12px;
            height: 12px;
            border: 1px solid #111827;
            display: inline-block;
            text-align: center;
            line-height: 10px;
            font-size: 10px;
        }
        .checked::after {
            content: "x";
        }
        .divider {
            border-top: 1px solid #111827;
            margin: 6px 0;
        }
        .notes-box {
            border: 1px solid #111827;
            min-height: 60px;
            padding: 6px;
            margin-top: 4px;
        }
        .signature {
            text-align: center;
            margin-top: 12px;
        }
        .signature .name {
            font-weight: 700;
        }
        .spacer {
            height: 6px;
        }
        .check-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Cetak</button>

    @foreach ($items as $item)
        @php
            $kegiatan = $item['kegiatan'];
        @endphp
        <div class="sheet">
            <div class="header">
                <div class="header-logo">
                    <img src="{{ asset('images/logo-wonosobo.png') }}" alt="Logo Wonosobo">
                </div>
                <div class="header-text">
                    <div class="line-1">PEMERINTAH KABUPATEN WONOSOBO</div>
                    <div class="line-2">KECAMATAN WATUMALANG</div>
                    <div class="line-3">Jalan Kyai Jebeng Lintang Nomor 29 Watumalang Wonosobo, Jawa Tengah, 56352</div>
                    <div class="line-4">Telpon ( 0286 ) 3304957</div>
                    <div class="line-5">Laman: kecamatanwatumalang.wonosobokab.go.id</div>
                    <div class="line-5">Pos-el watumalang08@gmail.com</div>
                </div>
            </div>

            <div class="divider"></div>
            <div class="title">LEMBAR DISPOSISI</div>
            <div class="divider"></div>

            <table class="grid">
                <tr>
                    <td class="label">Surat dari</td>
                    <td>: <span class="line">{{ $kegiatan->surat_dari ?? '' }}</span></td>
                    <td class="right-label">Diterima Tgl</td>
                    <td>: <span class="line">{{ $kegiatan->created_at?->format('d/m/Y') ?? '-' }}</span></td>
                </tr>
                <tr>
                    <td class="label">No Surat</td>
                    <td>: {{ $kegiatan->nomor ?? '-' }}</td>
                    <td class="right-label">No Agenda</td>
                    <td>: {{ $item['agenda_number'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Tgl Surat</td>
                    <td>: {{ $kegiatan->tanggal?->format('d/m/Y') ?? '-' }}</td>
                    <td class="right-label">Sifat</td>
                    <td>
                        <div class="check-inline">
                            <span class="checkbox"></span> Sangat Segera
                            <span class="checkbox"></span> Segera
                            <span class="checkbox"></span> Rahasia
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4"><div class="divider"></div></td>
                </tr>
                <tr>
                    <td class="label">Hal</td>
                    <td colspan="3">: <strong>{{ $kegiatan->nama_kegiatan ?? '-' }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4"><div class="divider"></div></td>
                </tr>
            </table>

            <div class="spacer"></div>

            <table class="grid">
                <tr>
                    <td style="width: 55%;">
                        <div class="section-title">Diteruskan kepada Sdr :</div>
                        <div class="checklist">
                            @foreach ($item['targets'] as $target)
                                <div class="check-item">
                                    <span class="checkbox {{ $target['checked'] ? 'checked' : '' }}"></span>
                                    <span>{{ $target['label'] }}</span>
                                </div>
                            @endforeach
                            <div class="check-item">
                                <span class="checkbox {{ $item['lainnya'] !== '' ? 'checked' : '' }}"></span>
                                <span>Lainnya</span>
                            </div>
                            <div class="line" style="min-width: 100%;">{{ $item['lainnya'] }}</div>
                        </div>
                    </td>
                    <td style="width: 45%;">
                        <div class="section-title">Dengan hormat harap</div>
                        <div class="checklist">
                            <div class="check-item"><span class="checkbox"></span> Tanggapan dan Saran</div>
                            <div class="check-item"><span class="checkbox"></span> Proses lebih lanjut</div>
                            <div class="check-item"><span class="checkbox"></span> Koordinasi/Konfirmasi</div>
                            <div class="spacer"></div>
                            <div class="spacer"></div>
                            <div class="check-item"><span class="line" style="min-width: 100%;"></span></div>
                            <div class="spacer"></div>
                            <div class="spacer"></div>
                            <div class="check-item"><span class="line" style="min-width: 100%;"></span></div>
                            
                           
                        </div>
                    </td>
                </tr>
            </table>

            <div class="spacer"></div>

            <table class="grid">
                <tr>
                    <td colspan="2"><div class="divider"></div></td>
                </tr>
                <tr>
                    <td style="width: 55%;">
                        <div class="section-title">Catatan :</div>
                        <div class="notes-box"></div>
                    </td>
                    <td style="width: 45%;">
                        <div class="signature">
                            <div>Camat Watumalang</div>
                            <div class="spacer"></div>
                            <div class="spacer"></div>
                            <div class="spacer"></div>
                            <div class="spacer"></div>
                            <div class="spacer"></div>
                            <div class="name">{{ $item['camat_nama'] !== '' ? $item['camat_nama'] : '-' }}</div>
                            <div>{{ $item['camat_pangkat'] !== '' ? $item['camat_pangkat'] : '-' }}</div>
                            <div>NIP. {{ $item['camat_nip'] !== '' ? $item['camat_nip'] : '-' }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
