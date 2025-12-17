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
- WhatsApp Gateway:
  - wa-gateway (disarankan): `WA_GATEWAY_BASE_URL`, `WA_GATEWAY_KEY` (opsional), `WA_GATEWAY_TOKEN`, `WA_GATEWAY_FINISH_WHITELIST`
  - Default grup WA diambil dari tabel Grup WA (centang "Jadikan grup default").
- PDF to text: `PDFTOTEXT_PATH=/usr/bin/pdftotext` (untuk Ubuntu)
  - `WA_GATEWAY_FINISH_WHITELIST` berisi nomor WA (format 62xxxx) yang tetap boleh menandai TL selesai di webhook, selain personil yang ditugaskan. Pisahkan dengan koma, contoh: `WA_GATEWAY_FINISH_WHITELIST=6281234567890,6289876543210`. Biarkan kosong jika tidak ingin whitelist tambahan.

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
