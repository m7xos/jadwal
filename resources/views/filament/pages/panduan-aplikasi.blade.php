<x-filament-panels::page>
    @php
        $imgBase = asset('images/panduan');
    @endphp

    <div class="space-y-10">
        <section class="space-y-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Panduan Aplikasi</h2>
                <p class="text-sm text-gray-600">Panduan lengkap menjalankan dan menggunakan aplikasi, disusun ringkas untuk pengguna pemula.</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>Catatan:</strong> Gambar di bawah adalah placeholder. Silakan ganti dengan screenshot asli agar panduan lebih jelas.
            </div>
        </section>

        <details class="group rounded-xl border border-gray-200 bg-white p-5 open:bg-gray-50">
            <summary class="cursor-pointer list-none text-lg font-semibold text-gray-900">
                Bagian 1 — Persiapan &amp; Akses
            </summary>
            <div class="mt-4 space-y-8">
                <section class="space-y-4">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">1.</span>
                        Menjalankan Aplikasi (Instalasi &amp; Server)
                    </h3>
                    <p class="text-sm text-gray-600">Ikuti langkah berikut saat pemasangan pertama atau saat menyiapkan server baru.</p>
                    <div class="space-y-3 text-sm text-gray-700">
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <p class="font-semibold text-gray-900">Prasyarat</p>
                            <ul class="mt-2 list-disc pl-5 space-y-1">
                                <li>PHP 8.3+, Composer, Node.js (untuk build aset).</li>
                                <li>Database (MySQL/PostgreSQL sesuai kebutuhan).</li>
                                <li>Tool <code>pdftotext</code> untuk ekstraksi PDF.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <p class="font-semibold text-gray-900">Instalasi</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><code>composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link</code></pre>
                            <p class="mt-2 text-xs text-gray-500">Sesuaikan <code>.env</code>: DB, APP_URL, APP_TIMEZONE, dan konfigurasi WA Gateway.</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <p class="font-semibold text-gray-900">Menjalankan Lokal (Pengembangan)</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><code>php artisan serve
npm run dev</code></pre>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <p class="font-semibold text-gray-900">Scheduler &amp; Webhook</p>
                            <p class="mt-2">Pastikan cron berjalan tiap menit untuk pengingat:</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><code>* * * * * cd /var/www/jadwal && /usr/bin/php artisan schedule:run &gt;&gt; storage/logs/schedule.log 2&gt;&amp;1</code></pre>
                            <p class="mt-2">Konfigurasi webhook WA Gateway:</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><code>POST https://&lt;APP_URL&gt;/api/wa-gateway/webhook/message</code></pre>
                        </div>
                    </div>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/01-instalasi.svg" alt="Contoh instalasi aplikasi" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: proses instalasi dan konfigurasi awal.</figcaption>
                    </figure>
                </section>

                <section class="space-y-4">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">2.</span>
                        Masuk ke Aplikasi
                    </h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-700">
                        <li>Buka URL aplikasi, misalnya <code>https://domain-anda/admin</code>.</li>
                        <li>Masukkan <strong>NIP</strong> dan password berupa <strong>nomor WA</strong> (format <code>628...</code>).</li>
                        <li>Klik tombol <strong>Masuk</strong>.</li>
                    </ol>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/02-login.svg" alt="Halaman login" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: halaman login.</figcaption>
                    </figure>
                </section>

                <section class="space-y-4">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">3.</span>
                        Profil Akun
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Profil Akun</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Perbarui nama, email, dan kata sandi.</li>
                            <li>Gunakan ketika ada pergantian admin/pengguna.</li>
                        </ul>
                    </div>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/22-profil-akun.svg" alt="Profil akun" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: halaman profil akun.</figcaption>
                    </figure>
                </section>

                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">4.</span>
                        Dashboard
                    </h3>
                    <p class="text-sm text-gray-700">Dashboard menampilkan ringkasan agenda, grafik kegiatan, dan statistik kendaraan.</p>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/03-dashboard.svg" alt="Dashboard aplikasi" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: ringkasan statistik pada dashboard.</figcaption>
                    </figure>
                </section>

                <section class="space-y-4">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">5.</span>
                        Tema Yield Panel
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Tema Yield Panel (ikon palet di topbar)</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Atur warna, font, dan ikon tema sesuai kebutuhan.</li>
                            <li>Perubahan tema langsung tersimpan.</li>
                        </ul>
                    </div>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/23-tema-yield-panel.svg" alt="Tema yield panel" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: pengaturan tema di topbar.</figcaption>
                    </figure>
                </section>
            </div>
        </details>

        <details class="group rounded-xl border border-gray-200 bg-white p-5 open:bg-gray-50">
            <summary class="cursor-pointer list-none text-lg font-semibold text-gray-900">
                Bagian 2 — Manajemen Kegiatan
            </summary>
            <div class="mt-4 space-y-8">
                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">6.</span>
                        Agenda Surat Masuk/Kegiatan
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Manajemen Kegiatan &rarr; Agenda Surat Masuk/Kegiatan</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Tambah kegiatan baru, atur tanggal, waktu, tempat, dan penanggung jawab.</li>
                            <li>Unggah PDF surat undangan agar nomor/perihal terisi otomatis.</li>
                            <li>Gunakan tombol preview untuk melihat surat, serta lampiran jika diperlukan.</li>
                            <li>Pilih semua pegawai untuk menugaskan seluruh personil sekaligus.</li>
                            <li>Tentukan grup WA tujuan agenda untuk kirim multi grup.</li>
                            <li>Aktifkan <strong>Tampilkan di dashboard publik</strong> jika agenda boleh tampil di halaman publik.</li>
                            <li>Gunakan filter Hari Ini, Filter Tanggal, Belum Disposisi, dan Surat Masuk (TL).</li>
                            <li>Status TL menandai tindak lanjut sudah/belum selesai.</li>
                            <li>Aksi per baris: Buat Surat Tugas, Buat SPPD, Kirim WA ke personil.</li>
                            <li>Aksi massal: Kirim rekap sesuai filter, Kirim daftar Belum Disposisi, Kirim WA multi grup.</li>
                        </ul>
                    </div>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/04-agenda-list.svg" alt="Daftar agenda kegiatan" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: daftar agenda kegiatan.</figcaption>
                    </figure>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/05-agenda-form.svg" alt="Form tambah/edit kegiatan" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: form tambah atau edit kegiatan.</figcaption>
                    </figure>
                </section>

                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">7.</span>
                        Pengingat Kegiatan Lainnya
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Manajemen Kegiatan &rarr; Pengingat Kegiatan Lainnya</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Pilih personil, nomor WA, dan metode kirim (japri/grup).</li>
                            <li>Isi nama kegiatan, tanggal, jam, tempat, dan keterangan.</li>
                            <li>Pengingat akan tercatat di log.</li>
                            <li>Gunakan aksi <strong>Kirim Sekarang</strong> untuk mengirim manual.</li>
                            <li>Gunakan aksi <strong>Tandai Selesai</strong> untuk menghentikan pengingat.</li>
                        </ul>
                    </div>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/06-pengingat-kegiatan-list.svg" alt="Daftar pengingat kegiatan lainnya" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: daftar pengingat kegiatan lainnya.</figcaption>
                    </figure>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/07-pengingat-kegiatan-form.svg" alt="Form pengingat kegiatan lainnya" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: form membuat pengingat kegiatan lainnya.</figcaption>
                    </figure>
                </section>

                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">8.</span>
                        Pajak Kendaraan
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Manajemen Kegiatan &rarr; Pajak Kendaraan</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Simpan data jatuh tempo pajak tahunan dan lima tahunan.</li>
                            <li>Status pembayaran bisa diubah ketika pajak sudah lunas.</li>
                            <li>Balasan WA <code>Pajak-NOMOR_POLISI terbayar</code> dapat menandai status lunas (jika webhook aktif).</li>
                        </ul>
                    </div>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/08-pajak-kendaraan-list.svg" alt="Daftar pajak kendaraan" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: daftar pajak kendaraan.</figcaption>
                    </figure>
                    <figure class="space-y-2">
                        <img src="{{ $imgBase }}/09-pajak-kendaraan-form.svg" alt="Form pajak kendaraan" class="w-full rounded-xl border border-gray-200">
                        <figcaption class="text-xs text-gray-500">Screenshot: form tambah/edit pajak kendaraan.</figcaption>
                    </figure>
                </section>
            </div>
        </details>

        <details class="group rounded-xl border border-gray-200 bg-white p-5 open:bg-gray-50">
            <summary class="cursor-pointer list-none text-lg font-semibold text-gray-900">
                Bagian 3 — Pengaturan Data
            </summary>
            <div class="mt-4 space-y-8">
                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">9.</span>
                        Data Kendaraan
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Pengaturan &rarr; Data Kendaraan</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Kelola data aset kendaraan beserta nomor polisi.</li>
                            <li>Gunakan tombol <strong>Import dari Excel/CSV</strong> jika data banyak.</li>
                            <li>Gunakan contoh template di folder <code>Public/Template</code> saat impor.</li>
                        </ul>
                    </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/10-data-kendaraan.svg" alt="Data kendaraan" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: data kendaraan dan tombol import.</figcaption>
            </figure>
                </section>

                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">10.</span>
                        Personil
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Pengaturan &rarr; Personil</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Isi nama, jabatan, NIP, dan nomor WA personil.</li>
                            <li>NIP dipakai untuk login dan nomor WA dipakai sebagai password.</li>
                            <li>Kategori digunakan untuk filter pengiriman pesan.</li>
                            <li>Import dari Excel untuk mempercepat input.</li>
                        </ul>
                    </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/11-personil.svg" alt="Daftar personil" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: daftar personil.</figcaption>
            </figure>
                </section>

                <section class="space-y-6">
                    <h3 class="text-xl font-semibold text-gray-900">
                        <span class="text-gray-500">11.</span>
                        Grup WA
                    </h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><strong>Menu:</strong> Pengaturan &rarr; Grup WA</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>Daftarkan grup WhatsApp yang dipakai untuk pengingat.</li>
                            <li>Isi <strong>ID Grup WA Gateway</strong> sesuai data dari WA Gateway.</li>
                            <li>Gunakan <strong>Jadikan grup default</strong> sebagai tujuan utama pengiriman.</li>
                            <li>Pilih salah satu grup sebagai default jika diperlukan.</li>
                        </ul>
                    </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/12-grup-wa.svg" alt="Grup WhatsApp" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: daftar grup WhatsApp.</figcaption>
            </figure>
                </section>

                <section class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">12.</span>
                Pengurus Barang
            </h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Menu:</strong> Pengaturan &rarr; Pengurus Barang</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Pilih personil yang menjadi pengurus barang.</li>
                    <li>Nomor WA akan dipakai pada pesan pengingat pajak kendaraan.</li>
                </ul>
            </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/13-pengurus-barang.svg" alt="Pengaturan pengurus barang" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: pengaturan pengurus barang.</figcaption>
            </figure>
                </section>

                <section class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">13.</span>
                Pengaturan Hak Akses
            </h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Menu:</strong> Pengaturan &rarr; Pengaturan Akses</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Atur halaman yang bisa diakses tiap role.</li>
                    <li>Admin disarankan memiliki akses penuh.</li>
                </ul>
            </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/14-pengaturan-akses.svg" alt="Pengaturan akses" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: pengaturan akses pengguna.</figcaption>
            </figure>
                </section>
            </div>
        </details>

        <details class="group rounded-xl border border-gray-200 bg-white p-5 open:bg-gray-50">
            <summary class="cursor-pointer list-none text-lg font-semibold text-gray-900">
                Bagian 4 — Laporan &amp; Log
            </summary>
            <div class="mt-4 space-y-8">
                <section class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">14.</span>
                Laporan Rekap Kegiatan
            </h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Menu:</strong> Laporan &rarr; Rekap Kegiatan</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Pilih bulan, lalu sistem membuat rekap otomatis.</li>
                    <li>Gunakan untuk pelaporan bulanan.</li>
                </ul>
            </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/15-laporan-rekap.svg" alt="Laporan rekap kegiatan" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: rekap kegiatan bulanan.</figcaption>
            </figure>
                </section>

                <section class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">15.</span>
                Laporan Pembayaran Pajak
            </h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Menu:</strong> Laporan &rarr; Laporan Pembayaran Pajak</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Melihat daftar pajak kendaraan yang akan jatuh tempo.</li>
                    <li>Filter status untuk melihat yang sudah lunas/belum.</li>
                </ul>
            </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/16-laporan-pajak.svg" alt="Laporan pembayaran pajak" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: laporan pembayaran pajak.</figcaption>
            </figure>
                </section>

                <section class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">16.</span>
                Log Pengingat
            </h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Menu:</strong> Log</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Log Pengingat TL: pantau status pengiriman pengingat tindak lanjut.</li>
                    <li>Log Pengiriman Pengingat Pajak: lihat hasil pengiriman WA.</li>
                    <li>Log Pengingat Kegiatan Lainnya: riwayat pengingat kegiatan tambahan.</li>
                    <li>Gunakan aksi <strong>Detail Respons</strong> untuk melihat balasan API.</li>
                    <li>Gunakan aksi <strong>Kirim Ulang</strong> jika pengiriman gagal.</li>
                    <li>Balasan WA <code>TL-*&nbsp;selesai</code> menandai tindak lanjut selesai (jika webhook aktif).</li>
                </ul>
            </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/17-log-pengingat-tl.svg" alt="Log pengingat tindak lanjut" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: log pengingat tindak lanjut.</figcaption>
            </figure>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/18-log-pengingat-pajak.svg" alt="Log pengingat pajak kendaraan" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: log pengiriman pengingat pajak.</figcaption>
            </figure>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/19-log-pengingat-kegiatan.svg" alt="Log pengingat kegiatan lainnya" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: log pengingat kegiatan lainnya.</figcaption>
            </figure>
                </section>
            </div>
        </details>

        <details class="group rounded-xl border border-gray-200 bg-white p-5 open:bg-gray-50">
            <summary class="cursor-pointer list-none text-lg font-semibold text-gray-900">
                Bagian 5 — Halaman Publik &amp; Tips
            </summary>
            <div class="mt-4 space-y-8">
                <section class="space-y-6">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">17.</span>
                Halaman Publik
            </h3>
            <div class="space-y-2 text-sm text-gray-700">
                <p><strong>Menu:</strong> Halaman Publik</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Beranda Website: halaman publik utama.</li>
                    <li>Agenda Publik: daftar agenda yang bisa dilihat masyarakat.</li>
                </ul>
            </div>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/20-beranda-publik.svg" alt="Beranda website publik" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: beranda publik.</figcaption>
            </figure>
            <figure class="space-y-2">
                <img src="{{ $imgBase }}/21-agenda-publik.svg" alt="Agenda publik" class="w-full rounded-xl border border-gray-200">
                <figcaption class="text-xs text-gray-500">Screenshot: agenda publik.</figcaption>
            </figure>
                </section>

                <section class="space-y-4">
            <h3 class="text-xl font-semibold text-gray-900">
                <span class="text-gray-500">18.</span>
                Tips Pemakaian untuk Pemula
            </h3>
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700">
                <li>Jika menu tidak muncul, kemungkinan akses Anda dibatasi. Minta admin mengaktifkannya.</li>
                <li>Gunakan format nomor WA <code>628...</code> agar pesan WA terkirim.</li>
                <li>Rutin cek menu Log untuk memastikan pengingat terkirim.</li>
                <li>Gunakan fitur import untuk data banyak agar lebih cepat.</li>
            </ul>
                </section>
            </div>
        </details>
    </div>
</x-filament-panels::page>
