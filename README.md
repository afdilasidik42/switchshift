# Roti’O Shift Management

Aplikasi lokal Laravel 13, Inertia 2, React 19, TypeScript, dan MySQL/MariaDB. Jadwal disimpan di database.

## Kebutuhan

PHP 8.3 dengan pdo_mysql, mbstring, openssl, xml, curl, zip, fileinfo; Composer 2; Node.js 20+ dan npm; MySQL atau MariaDB.

## Setup lokal

1. Jalankan database. Buat dua database kosong terpisah, misalnya rotio_dev dan rotio_test. Jangan gunakan database yang berisi data pengguna untuk reset atau seeding.
2. Jalankan `composer install` dan `npm ci`.
3. Salin `.env.example` ke `.env`. Isi host, port, nama database pengembangan, username, dan password database. Jalankan `php artisan key:generate`.
4. Jalankan `php artisan migrate`.
5. Jalankan `php artisan rotio:admin`. Masukkan ID, nama, dan password sementara minimal 12 karakter melalui prompt. Password tidak muncul di terminal.
6. Jika ingin data simulasi, hanya pada database kosong, isi `SEED_TEMP_PASSWORD` di lingkungan proses lalu jalankan `php artisan db:seed`. Seeder membuat satu outlet, SPV, dan 12 karyawan. Semua akun wajib mengganti password pada login pertama. Hapus variabel lingkungan proses setelah selesai.
7. Jalankan `npm run all` untuk menyalakan Laravel, Vite, dan scheduler dalam satu terminal. Buka http://127.0.0.1:8000. Hentikan ketiganya dengan Ctrl+C. Untuk aset hasil build tanpa Vite, jalankan `npm run build` sebelum `php artisan serve`.

Scheduler menutup pengajuan kadaluarsa berkala. Tindakan terlambat juga diperiksa langsung oleh server.

## Pengujian

Isi kredensial database **uji** pada `phpunit.xml` atau environment proses sebelum `php artisan test`. Gunakan database terpisah yang boleh direset oleh test. Jalankan `npm run typecheck`, `npm run build`, dan `vendor/bin/pint --test`.

## Backup lokal

Jalankan `mysqldump --single-transaction --routines --triggers -u USER -p rotio_dev > backup.sql`. Simpan dump di lokasi terbatas di luar repo. Untuk menguji pemulihan, buat database uji pemulihan yang kosong, jalankan `mysql -u USER -p rotio_restore < backup.sql`, lalu periksa jumlah akun, jadwal, dan riwayat pada database tersebut. Jangan menimpa database aktif.

## Catatan keputusan

Satu outlet operasional memiliki satu SPV dan satu SPV ditugaskan pada satu outlet. Karyawan tidak dapat pindah outlet. Penonaktifan mencabut sesi, mempertahankan riwayat, dan mengeluarkan karyawan dari hitungan sejak nonaktif sesuai keputusan pengguna. Batas empat hari kerja diperiksa lintas minggu. Tenggat pertukaran memakai tengah malam waktu outlet pada tanggal kerja pertama.

Aplikasi ini memerlukan pengujian alur dan review operasional sebelum dipakai sebagai jadwal resmi. Verifikasi browser yang dilakukan saat implementasi baru mencakup layar login pada viewport laptop dan 390 px. Keputusan bersamaan, backup–restore, dan seluruh acceptance criteria PRD belum terverifikasi. Data seed yang dibuat sebelum perubahan pada seeder tidak memiliki audit penerbitan awal; instalasi baru menggunakan seeder terbaru.
