# Jadwal TL Reminders

Panduan singkat memasang aplikasi dan scheduler di server Ubuntu.

## Instalasi server baru
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
- Wablas: `WABLAS_BASE_URL`, `WABLAS_TOKEN`, `WABLAS_SECRET_KEY` (opsional), `WABLAS_GROUP_ID`, `WABLAS_FINISH_WHITELIST` (comma separated, optional)
- PDF to text: `PDFTOTEXT_PATH=/usr/bin/pdftotext` (untuk Ubuntu)

## Webhook Wablas (wajib)
- Endpoint utama: `POST https://<APP_URL>/api/wablas/webhook`.
- Endpoint legacy (masih tersedia, tanpa prefix API): `POST https://<APP_URL>/wablas/webhook`. Pilih salah satu; disarankan memakai endpoint utama `/api/wablas/webhook`.
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
- Scheduler menjalankan pengingat TL (awal H-5 jam, akhir saat batas TL) dan webhook Wablas menangani balasan “TL-{id} selesai”.
- Jika device Wablas atau config tidak lengkap, pengiriman gagal dan status log menjadi failed; kirim ulang via aksi “Kirim Ulang” di Log Pengingat TL.
