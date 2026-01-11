<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tanda Register Layanan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('components.public-icons')
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #0f172a;
            margin: 24px;
        }
        .card {
            border: 1px solid #cbd5f5;
            border-radius: 12px;
            padding: 16px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 12px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 8px;
        }
        .label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .value {
            font-size: 14px;
            font-weight: 600;
        }
        .note {
            margin-top: 12px;
            font-size: 12px;
            color: #475569;
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

    <div class="card">
        <div class="title">Tanda Register Layanan Publik</div>

        <div class="row">
            <div>
                <div class="label">Kode Register</div>
                <div class="value">{{ $layanan->kode_register }}</div>
            </div>
            <div>
                <div class="label">Tanggal Masuk</div>
                <div class="value">{{ $layanan->tanggal_masuk?->format('d/m/Y') ?? '-' }}</div>
            </div>
        </div>

        <div class="row">
            <div>
                <div class="label">No Antrian</div>
                <div class="value">{{ $layanan->queue_number ?? '-' }}</div>
            </div>
            <div>
                <div class="label">Status</div>
                <div class="value">{{ $layanan->status_label }}</div>
            </div>
        </div>

        <div class="row">
            <div>
                <div class="label">Nama Pemohon</div>
                <div class="value">{{ $layanan->nama_pemohon }}</div>
            </div>
            <div>
                <div class="label">Layanan</div>
                <div class="value">{{ $layanan->layanan?->nama ?? '-' }}</div>
            </div>
        </div>

        <div class="row">
            <div>
                <div class="label">Kategori</div>
                <div class="value">{{ $layanan->layanan?->kategori ?? '-' }}</div>
            </div>
        </div>

        <div class="row">
            <div>
                <div class="label">Nomor WA Pemohon</div>
                <div class="value">{{ $layanan->no_wa_pemohon ?? '-' }}</div>
            </div>
        </div>

        <div class="note">
            Simpan tanda register ini untuk mengecek status layanan.
        </div>
    </div>
</body>
</html>
