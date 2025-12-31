# jadwal

Aplikasi Android untuk Jadwal Watumalang (personil internal).

## Konfigurasi cepat
- API base URL (default `https://your-domain.test/api/v1`) bisa diganti saat run/build:
  ```
  flutter run --dart-define=API_BASE_URL=https://domain-anda.test/api/v1
  ```
- Firebase Cloud Messaging (wajib untuk push):
  1) Unduh `google-services.json` dari Firebase Console.
  2) Simpan ke `android/app/google-services.json`.
  3) Jalankan `flutter pub get` dan build ulang.
- Ikon aplikasi:
  1) Ganti `assets/logo.png` dengan logo resmi.
  2) Jalankan: `flutter pub run flutter_launcher_icons`.
  - Saat ini `assets/logo.png` masih placeholder, silakan ganti dengan logo yang dilampirkan.

## Catatan
- Login memakai NIP dan password (default password masih nomor WA).
- Push notifikasi akan aktif setelah token device berhasil didaftarkan ke API.
  - Jika API masih HTTP (tanpa SSL), tambahkan `android:usesCleartextTraffic="true"` di `android/app/src/main/AndroidManifest.xml`.
