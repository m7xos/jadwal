# Jadwal TL Reminders

Panduan singkat memasang aplikasi dan scheduler di server Ubuntu.

## Instalasi cepat (production, Apache + PHP-FPM 8.4 + MariaDB)
- Jalankan sebagai user deploy (script akan meminta sudo saat perlu):
  ```
  DEPLOY_USER=deploy \
  APP_USER=www-data \
  APP_DIR=/var/www/jadwal \
  DOMAIN=example.com \
  DB_NAME=jadwal \
  DB_USER=jadwal \
  DB_PASS=supersecret \
  scripts/install-production.sh
  ```
- Script akan:
  - Tambah repo Sury (jika belum ada), install Apache, MariaDB, PHP-FPM 8.4 + ekstensi, ACL.
  - Buat vhost Apache ke `${APP_DIR}/public` via socket `php8.4-fpm`.
  - Buat database & user MariaDB (idempotent).
  - Set izin `storage/` & `bootstrap/cache` rwx untuk `www-data` dan `deploy`, serta `.env` rw.
  - `composer install --no-dev` dan artisan cache (key:generate, migrate, config/route/view cache).
- Setelah selesai: sesuaikan `.env` (APP_URL/DB) lalu `sudo systemctl reload apache2 php8.4-fpm` jika perlu.

## Instalasi server baru (manual)
- Prasyarat: PHP 8.3+ (CLI), Composer, Node (untuk build front-end jika diperlukan), Git, dan `pdftotext`.
- Clone repo, lalu:
  - `composer install`
  - `npm install && npm run build` (jika butuh aset produksi)
  - `cp .env.example .env` kemudian isi konfigurasi.
  - `php artisan key:generate`
  - `php artisan migrate`
  - `php artisan storage:link`

## Konfigurasi .env penting
- Database: `DB_*`
- App: `APP_URL`, `APP_TIMEZONE`
- WhatsApp Gateway:
  - Pengaturan kini dikelola di menu **Pengaturan -> WA Gateway** (database).
  - Nilai di `.env` tetap menjadi fallback awal saat tabel belum terisi.
  - Jika wa-gateway mengaktifkan `KEY` (production), isi `WA_GATEWAY_KEY` agar request tidak 401.
  - Token device diisi manual dari wa-gateway (tidak ada registry sync).
  - Default grup WA diambil dari tabel Grup WA (centang "Jadikan grup default").
  - Template pesan WhatsApp bisa diubah di menu **Pengaturan -> Template Pesan WA** (kosongkan untuk kembali ke default).
- PDF to text: `PDFTOTEXT_PATH=/usr/bin/pdftotext` (untuk Ubuntu)
  - Whitelist penyelesaian TL di menu **Pengaturan -> WA Gateway** berisi nomor WA (format 62xxxx) yang tetap boleh menandai TL selesai di webhook, selain personil yang ditugaskan. Pisahkan dengan koma, contoh: `6281234567890,6289876543210`. Biarkan kosong jika tidak ingin whitelist tambahan.

## Webhook WhatsApp (wajib)
- Untuk wa-gateway: set `webhookBaseUrl = ${APP_URL}/wa-gateway/webhook` di wa-gateway (ia akan POST ke `${webhookBaseUrl}/message`).
  - Endpoint: `POST https://<APP_URL>/api/wa-gateway/webhook/message` (atau legacy tanpa prefix API: `POST https://<APP_URL>/wa-gateway/webhook/message`).
- Pesan masuk grup dengan teks `TL-<id> selesai` (atau mengandung “selesai” + kode TL) akan menandai surat selesai TL bila nomor pengirim diizinkan (penerima TL atau jabatan Arsiparis/Pranata Komputer, atau nomor owner/sender grup).

## Cron scheduler
- Jalankan `artisan schedule:run` tiap menit dengan user yang punya izin tulis ke `storage/logs` (contoh www-data):
  - `sudo crontab -u www-data -e`
  - Tambahkan:
    ```
    * * * * * cd /var/www/jadwal && /usr/bin/php artisan schedule:run >> /var/www/jadwal/storage/logs/schedule.log 2>&1
    ```
- Pastikan path PHP sesuai (`which php`) dan folder proyek/log bisa ditulis user cron.

## Catatan operasional
- Scheduler menjalankan pengingat TL (awal H-5 jam, akhir saat batas TL) dan webhook wa-gateway menangani balasan "TL-{id} selesai".
- Jika device wa-gateway atau konfigurasi tidak lengkap, pengiriman gagal dan status log menjadi failed; kirim ulang via aksi "Kirim Ulang" di Log Pengingat TL.
- Pengingat pajak kendaraan: job `vehicle-taxes:send-reminders` jalan setiap 08:00 WIB untuk H-7/H-3/H0 (pajak tahunan & 5 tahunan) dan mencatat Log Pengingat Pajak. Gunakan `--force` dan `--date=YYYY-MM-DD` untuk uji manual.
- Pembayaran pajak: balas pesan masuk ke webhook wa-gateway dengan pola `Pajak-{NOMOR_POLISI} terbayar` untuk menandai status pajak kendaraan menjadi LUNAS dan mengirim balasan terima kasih.

## Nomor Surat Keluar via WhatsApp
- Ketik di grup: **minta nomor surat keluar** → sistem akan membalas ke chat pribadi untuk input kode klasifikasi dan hal surat.
- Data kode klasifikasi diambil dari tabel `kode_surats`. Untuk impor dari Excel:
  - Simpan file `kode_klasifikasi.xlsx` ke `storage/app/`.
  - Jalankan: `php artisan kode-surat:import`
- Nomor surat susulan (sisipan) bisa dibuat lewat menu **Surat Keluar** di dashboard.
