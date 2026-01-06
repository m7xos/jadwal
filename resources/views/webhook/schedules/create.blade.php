<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Jadwal</title>
    <style>
        :root {
            --bg: #0b1622;
            --panel: #0f253a;
            --text: #e5edf5;
            --muted: #9fb4c9;
            --accent: #57c2ff;
            --border: #18344f;
            --danger: #ff7b7b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }
        h1 { margin: 0 0 8px; letter-spacing: -0.01em; }
        .note { color: var(--muted); margin-bottom: 18px; }
        form {
            background: rgba(15, 37, 58, 0.9);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            display: grid;
            gap: 14px;
        }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        input, textarea, select {
            width: 100%;
            background: var(--panel);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
        }
        textarea { min-height: 90px; }
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }
        button {
            background: linear-gradient(135deg, var(--accent), #4aa0e2);
            color: #0c1928;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            box-shadow: 0 10px 25px rgba(87, 194, 255, 0.25);
        }
        button:hover { transform: translateY(-1px); }
        button:active { transform: translateY(0); }
        .status {
            padding: 12px 14px;
            background: rgba(87, 194, 255, 0.12);
            border: 1px solid rgba(87, 194, 255, 0.25);
            border-radius: 10px;
            color: var(--text);
            margin-bottom: 12px;
        }
        .status.error {
            background: rgba(255, 123, 123, 0.15);
            border-color: rgba(255, 123, 123, 0.3);
        }
        .link {
            color: var(--accent);
            text-decoration: none;
        }
        ul { margin: 6px 0 0 18px; color: var(--text); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Buat Jadwal</h1>
    <div class="note">Form cepat untuk menambahkan data ke tabel <code>schedules</code>. Gunakan hanya di lingkungan dev/internal.</div>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="status error">
            <strong>Validasi gagal:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('webhook.schedules.store') }}" method="POST">
        @csrf
        <div>
            <label for="id_group">ID Group (JID)</label>
            <input id="id_group" name="id_group" type="text" placeholder="contoh: 120363406847354896@g.us" value="{{ old('id_group') }}" required>
        </div>
        <div>
            <label for="title">Judul</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" required>
        </div>
        <div class="row">
            <div>
                <label for="starts_at">Tanggal & Waktu</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at') }}" required>
            </div>
            <div>
                <label for="location">Lokasi (opsional)</label>
                <input id="location" name="location" type="text" value="{{ old('location') }}">
            </div>
        </div>
        <div>
            <label for="notes">Catatan (opsional)</label>
            <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
        </div>
        <div>
            <label for="is_disposed">Status disposisi</label>
            <select id="is_disposed" name="is_disposed">
                <option value="0" {{ old('is_disposed') == '0' ? 'selected' : '' }}>Belum disposisi</option>
                <option value="1" {{ old('is_disposed') == '1' ? 'selected' : '' }}>Sudah disposisi</option>
            </select>
        </div>
        <button type="submit">Simpan Jadwal</button>
    </form>

    <p class="note" style="margin-top:16px;">
        Balasan otomatis WA: kirim perintah di grup seperti "agenda hari ini/besok", "agenda belum disposisi hari ini/besok", atau "agenda belum disposisi +7/-7".
    </p>
</div>
</body>
</html>
