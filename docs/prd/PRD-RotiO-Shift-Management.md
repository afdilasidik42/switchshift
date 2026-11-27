# PRD Roti’O Shift Management

Versi: 1.3  
Tanggal: 26 November 2026  
Status: Acuan implementasi lokal; keputusan terbuka dicatat pada bagian 18  
Pemilik produk: Tim proyek bersama perwakilan perusahaan dan SPV outlet

## 1. Tujuan dan dasar dokumen

Dokumen ini menentukan kebutuhan produk untuk melanjutkan prototype Roti’O menjadi aplikasi pengelolaan jadwal dan pertukaran shift yang dapat diuji langsung oleh pengguna outlet. Pengembangan dimulai secara lokal, dengan satu outlet pilot dan kemampuan menambahkan outlet berikutnya.

Keputusan dalam percakapan penyusunan PRD menjadi acuan utama. Prototype menjadi referensi tampilan dan navigasi, bukan sumber kebenaran untuk aturan operasional. SRS lama berfokus pada broadcast; aturan khusus broadcast tidak dipindahkan ke produk ini.

Sumber lokal yang ditinjau:

- `Roti’O Shift Management – Prototype.html`: referensi layar dan interaksi awal.
- `Laporan Akhir - SRS Sistem Pengelolaan Perubahan Jadwal Shift.docx` dan versi PDF: referensi konsep persetujuan, riwayat, dan konfirmasi, dengan konteks berbeda.
- `PPT Proyek TI .pdf`: konteks perencanaan proyek dan pembagian kerja.
- `ERD Sistem Pertukaran Shift Roti'O.png`: referensi awal relasi; perlu direvisi karena masih memuat kebutuhan broadcast.

Istilah keputusan:

- **Disepakati:** ditentukan pengguna atau rekomendasi yang telah diterima.
- **Rancangan implementasi:** pilihan teknis atau detail perilaku untuk memenuhi keputusan; dapat disesuaikan tanpa mengubah kebutuhan produk.
- **Terbuka:** belum tersedia jawaban; tidak boleh dianggap sebagai aturan operasional yang telah tervalidasi.

PRD ini tidak menyatakan aplikasi sudah dibangun, lolos pengujian, atau disetujui perusahaan untuk menjadi jadwal operasional resmi.

## 2. Masalah, tujuan, dan ukuran keberhasilan

### 2.1 Masalah

1. Jadwal dan revisinya tersebar sehingga pengguna sulit mengetahui versi yang berlaku.
2. Pertukaran shift membutuhkan koordinasi manual yang sulit ditelusuri.
3. Pengajuan menunggu keputusan tanpa antrean tindakan yang jelas.
4. Perubahan jadwal belum tentu diketahui karyawan terkait.

### 2.2 Tujuan produk

- Menyediakan satu jadwal aktif yang tersimpan dan dapat dilihat pengguna sesuai outletnya.
- Menjalankan pertukaran dengan persetujuan rekan lalu SPV, tanpa perubahan sepihak.
- Menampilkan pengajuan yang perlu ditindaklanjuti dan batas waktunya.
- Menyimpan jejak perubahan serta konfirmasi penerimaan informasi.
- Memisahkan akses antar-outlet sejak awal.

### 2.3 Kriteria keberhasilan pilot

| Area | Kriteria penerimaan |
| --- | --- |
| Konsistensi | Tidak ada pertukaran tersimpan hanya pada satu sisi; jadwal dan status keputusan konsisten |
| Aturan operasional | Semua jadwal tersimpan memenuhi komposisi personel, batas harian, dan hari libur |
| Akses | Skenario akses lintas outlet dan tindakan di luar role ditolak server |
| Alur utama | Pengguna uji menyelesaikan input jadwal, pertukaran, pembatalan, dan konfirmasi sesuai skenario |
| Keterlacakan | Setiap perubahan jadwal memiliki pelaku, alasan, waktu, serta nilai sebelum dan sesudah |
| Informasi | Penerima yang berhak mendapat notifikasi dan SPV dapat melihat konfirmasi yang belum selesai |

Waktu penyelesaian approval, jumlah pengajuan kedaluwarsa, dan waktu konfirmasi dicatat selama pilot. Target peningkatan numeriknya belum ditetapkan karena baseline belum tersedia. Aplikasi tidak menjamin SPV selalu merespons tepat waktu.

## 3. Lingkup versi pertama

### 3.1 Termasuk

- Login, logout, ganti password, dan reset password melalui admin.
- Pengelolaan outlet, posisi, akun, penugasan SPV, dan penempatan karyawan.
- Satu outlet pilot dengan struktur data untuk banyak outlet.
- Jadwal mingguan, dua shift tetap, serta hari libur.
- Pertukaran dalam outlet yang sama dan posisi yang sama, termasuk beda tanggal serta kerja dengan libur.
- Persetujuan rekan dan SPV, penolakan, kedaluwarsa, dan pembatalan.
- Perubahan manual jadwal oleh SPV sesuai batas waktu dan validasi.
- Notifikasi dalam aplikasi, konfirmasi perubahan, dan riwayat.
- Dashboard sesuai role dan tampilan yang dapat dipakai di HP serta laptop.
- Penyimpanan permanen pada database lokal dan panduan menjalankan aplikasi.

### 3.2 Tidak termasuk

- Pertukaran dan perpindahan karyawan antar-outlet.
- Absensi, payroll, lembur, cuti/izin formal, dan penggantian darurat otomatis.
- Impor Excel, ekspor laporan, atau penjadwalan otomatis.
- WhatsApp, email, SMS, browser push, atau kanal notifikasi eksternal.
- Modul keahlian broadcast, tugas luar kantor, dan shift broadcast.
- Pendaftaran akun mandiri, aplikasi mobile native, dan integrasi sistem perusahaan.
- Hosting publik atau deployment produksi pada tahap ini.

## 4. Pengguna dan hak akses

### 4.1 Role

| Tindakan | Karyawan | SPV | Admin perusahaan |
| --- | --- | --- | --- |
| Melihat jadwal | Outlet sendiri | Outlet yang ditugaskan | Ringkasan lintas outlet untuk administrasi |
| Menyusun/mengubah jadwal | Tidak | Outlet yang ditugaskan | Tidak |
| Mengajukan pertukaran | Ya, jadwal sendiri | Tidak | Tidak |
| Merespons sebagai rekan | Hanya jika menjadi rekan yang dipilih | Tidak | Tidak |
| Memutuskan pertukaran/pembatalan | Tidak | Outlet yang ditugaskan | Tidak |
| Melihat alasan pribadi pengajuan | Pengaju dan rekan terkait | Outlet yang ditugaskan | Tidak |
| Mengelola outlet, posisi, akun | Tidak | Tidak | Ya |
| Memindahkan karyawan antar-outlet | Tidak | Tidak | Tidak |
| Menugaskan SPV | Tidak | Tidak | Ya |
| Reset password akun lain | Tidak | Tidak | Ya |
| Mengubah/menghapus audit | Tidak | Tidak | Tidak |

Satu outlet operasional dikelola tepat satu SPV aktif. Satu SPV hanya ditugaskan ke satu outlet dan tidak dapat melihat atau mengelola outlet lain. Admin perusahaan mengatur penugasan ini. Request berulang tetap harus menghasilkan tepat satu keputusan. Outlet baru belum dapat menjalankan proses operasional sebelum SPV ditetapkan.

SPV tidak masuk jadwal karyawan dan tidak dihitung sebagai Kasir/Baker untuk memenuhi minimum personel shift.

### 4.2 Keanggotaan outlet dan tanggal bergabung

- Admin menetapkan satu outlet, satu posisi aktif, dan tanggal mulai untuk setiap karyawan baru.
- Tidak tersedia perpindahan atau mutasi karyawan antar-outlet, termasuk oleh admin. Pertukaran hanya mengubah jadwal di outlet yang sama.
- Outlet karyawan yang sudah terdaftar tidak dapat diubah melalui form ataupun request langsung.
- Karyawan baru dapat mulai langsung pada tanggal bergabung. Hari sebelum bergabung ditandai belum bergabung, bukan hari libur dan bukan penugasan kosong yang wajib diisi.
- Jika bergabung Senin sampai Kamis, karyawan tetap mendapat tepat dua hari libur pada sisa minggu tersebut, ditentukan SPV. Hari lainnya sejak tanggal bergabung dijadwalkan kerja, dengan seluruh validasi personel dan batas empat hari berturut-turut tetap berlaku.
- Jika bergabung Jumat, Sabtu, atau Minggu, karyawan belum mendapat hari libur pada minggu pertama: seluruh hari sejak tanggal bergabung sampai Minggu dijadwalkan kerja. Mulai minggu berikutnya berlaku tepat dua hari libur. Minggu pertama yang tidak penuh tidak diwajibkan lima hari kerja.
- Batas empat hari kerja berturut-turut tetap berlaku lintas minggu. Contoh mulai Jumat dan bekerja Jumat–Senin: Selasa wajib libur; batas mingguan tidak mereset rangkaian kerja.
## 5. Data master dan aturan jadwal

### 5.1 Outlet dan posisi

- Outlet memiliki kode unik, nama, zona waktu, dan status aktif/nonaktif.
- Zona waktu dipilih dari Asia/Jakarta, Asia/Makassar, atau Asia/Jayapura.
- Posisi awal adalah **Kasir** dan **Baker**. Admin dapat mengelola daftar posisi, tetapi aturan minimum tetap secara eksplisit mengacu pada Kasir dan Baker.
- Setiap shift wajib memiliki minimal **1 Kasir dan 2 Baker**, bukan sekadar tiga orang dengan posisi bebas.
- Penambahan posisi baru tidak otomatis membuat posisi itu dapat menggantikan Kasir atau Baker.
- Perubahan posisi karyawan yang berpengaruh pada jadwal mendatang harus ditahan sampai jadwal dan pengajuan terkait diselesaikan. Audit lama tetap menyimpan posisi pada waktu kejadian.
- Master yang sudah dipakai tidak dihapus secara permanen melalui aplikasi. Penonaktifan outlet ditahan jika masih ada penempatan, jadwal mendatang, atau pengajuan aktif yang harus diselesaikan.

### 5.2 Kalender kerja

| Ketentuan | Nilai |
| --- | --- |
| Periode jadwal | Senin sampai Minggu |
| Pagi | 07.00–15.00 waktu outlet |
| Sore | 15.00–23.00 waktu outlet |
| Penugasan harian | Maksimal satu shift kerja per karyawan per tanggal |
| Hari libur | Dua hari per minggu; tanggalnya ditentukan SPV |
| Hari kerja berturut-turut | Maksimal empat hari, termasuk lintas minggu |
| Hari operasional outlet | Setiap hari, tujuh hari per minggu |
| Minimum tiap shift | Satu Kasir dan dua Baker aktif |
| Publikasi | Langsung setelah penyimpanan mingguan berhasil |

Rancangan validasi menerjemahkan dua hari libur sebagai tepat dua hari libur dan lima hari kerja pada minggu penuh. Setiap hari harus berisi Pagi, Sore, atau Libur; sel kosong bukan hari libur. Aturan ini juga diperiksa setelah pertukaran atau pemulihan pembatalan, termasuk kedua minggu jika tanggalnya lintas minggu.

Validasi maksimal empat hari kerja berturut-turut memeriksa rangkaian penugasan sebelum dan sesudah tanggal terdampak, termasuk minggu lain. Jadwal yang belum tersedia tidak dianggap sebagai libur; minggu berikutnya harus memeriksa rangkaian yang sudah tersimpan. Untuk seluruh karyawan baru, validasi minggu pertama mengikuti bagian 4.2: mulai Senin–Kamis mendapat dua hari libur, mulai Jumat–Minggu tanpa libur pada minggu pertama. Saat penonaktifan, riwayat tetap utuh dan karyawan keluar dari perhitungan hari libur serta kebutuhan personel sejak tanggal nonaktif.

### 5.3 Kelayakan jumlah karyawan

Dengan asumsi outlet beroperasi tujuh hari, kedua shift wajib terisi setiap hari, dan setiap karyawan bekerja lima hari:

- Kasir membutuhkan 2 × 7 × 1 = 14 penugasan per minggu. Minimum kapasitas: pembulatan ke atas 14 / 5 = **3 Kasir**.
- Baker membutuhkan 2 × 7 × 2 = 28 penugasan per minggu. Minimum kapasitas: pembulatan ke atas 28 / 5 = **6 Baker**.
- Minimum kapasitas total adalah **9 karyawan**, belum termasuk ketidakhadiran atau batas tambahan.

Angka ini merupakan hasil hitungan untuk pemeriksaan kapasitas, bukan jumlah karyawan aktual outlet. Jumlah minimum tidak menjamin setiap susunan jadwal valid. Sistem menampilkan kekurangan kapasitas dan tidak menurunkan persyaratan agar jadwal bisa disimpan.

### 5.4 Menyusun dan menyimpan jadwal

1. SPV membuka outlet tunggal yang ditugaskan kepadanya dan memilih minggu tujuan.
2. Editor memuat anggota sesuai penempatan dan menampilkan Pagi/Sore/Libur per tanggal.
3. Perubahan belum terlihat pengguna lain sebelum SPV menekan Simpan Jadwal.
4. Server memvalidasi seluruh minggu, penempatan, posisi, hari libur, jumlah personel, serta versi data.
5. Jika gagal, tidak ada perubahan yang disimpan. Pesan menampilkan tanggal, shift, atau karyawan yang bermasalah.
6. Jika berhasil, satu transaksi menyimpan jadwal, audit, notifikasi, dan kebutuhan konfirmasi.
7. Jadwal baru langsung terlihat. Tidak ada tahap publikasi terpisah.

Penyuntingan minggu berjalan mempertahankan hari yang sudah lewat dan shift yang sudah mulai. SPV hanya dapat mengubah shift yang belum mulai. Alasan wajib untuk revisi; perubahan hari H diizinkan selama belum mulai dan tetap memenuhi seluruh aturan. Penyimpanan tanpa perubahan tidak membuat versi, notifikasi, atau konfirmasi baru.

## 6. Pertukaran shift

### 6.1 Bentuk pertukaran

- Dua karyawan aktif, berbeda orang, posisi sama, dan outlet sama pada tanggal-tanggal terkait.
- Pertukaran pagi dengan sore pada tanggal yang sama diperbolehkan jika hasilnya valid.
- Pertukaran dua penugasan kerja pada tanggal berbeda diperbolehkan jika hasilnya valid.
- Pertukaran kerja dengan libur dilakukan sebagai pertukaran timbal balik yang jelas, bukan menyerahkan pekerjaan tanpa balasan.

Contoh kerja dengan libur: Senin A Pagi dan B Libur; Selasa A Libur dan B Sore. Setelah disetujui, Senin B Pagi dan A Libur; Selasa A Sore dan B Libur. Sistem menyimpan empat keadaan harian yang berubah sebagai satu perubahan utuh.

Penugasan pekerjaan mempertahankan tanggal, waktu shift, outlet, dan kebutuhan posisi; yang berubah adalah petugas serta status harian terkait. UI menampilkan tabel sebelum dan sesudah untuk kedua orang. Dua hari Libur tidak dapat ditukar, dan pertukaran yang tidak mengubah jadwal ditolak.

### 6.2 Syarat kelayakan

- Kedua jadwal sudah tersimpan dan belum dimulai.
- Tidak melewati batas H-1.
- Kedua akun aktif dan penempatannya cocok.
- Posisi sama, termasuk ketika salah satu objek berupa hari libur.
- Seluruh tanggal terdampak tetap maksimal satu shift per orang dan memenuhi komposisi minimum.
- Dua hari libur mingguan tetap terpenuhi pada setiap minggu terdampak.
- Tidak menghasilkan lebih dari empat hari kerja berturut-turut, termasuk pada batas minggu.
- Tidak ada perubahan versi jadwal sejak pengajuan.
- Satu objek jadwal, termasuk hari libur yang terlibat, hanya boleh berada dalam satu pengajuan aktif.
- Pengajuan aktif meliputi menunggu rekan, menunggu SPV, dan proses pembatalan atas jadwal yang akan dipulihkan.

Pemeriksaan dilakukan saat pengajuan, respons rekan, serta keputusan SPV. Pemeriksaan terakhir di server adalah penentu; pilihan yang sempat terlihat tersedia di browser tidak menjamin masih tersedia ketika dikirim.

### 6.3 Batas waktu

Batas eksklusif adalah pukul **00.00 pada tanggal shift kerja paling awal dalam pertukaran**, menggunakan zona waktu outlet. Pengajuan dan seluruh persetujuan harus terjadi sebelum batas itu. Contoh shift pertama Rabu: tindakan terakhir masih dapat diterima Selasa 23.59.59; Rabu 00.00.00 sudah ditolak.

Sistem menghitung batas dari waktu server, bukan jam perangkat. Request yang sampai setelah batas ditolak walaupun halaman dibuka sebelumnya. Hari libur tanpa jam kerja tidak dipakai sebagai pengganti tanggal shift kerja paling awal.

### 6.4 Status pertukaran

| Status | Tindakan yang diperbolehkan | Hasil |
| --- | --- | --- |
| Menunggu rekan | Rekan menerima | Menunggu SPV; jadwal tetap |
| Menunggu rekan | Rekan menolak dengan alasan | Ditolak; jadwal tetap |
| Menunggu rekan / SPV | Pengaju membatalkan dengan alasan | Dibatalkan; jadwal tetap |
| Menunggu SPV | SPV menyetujui | Disetujui; seluruh perubahan jadwal tersimpan bersama |
| Menunggu SPV | SPV menolak dengan alasan | Ditolak; jadwal tetap |
| Menunggu rekan / SPV | Batas waktu tercapai | Kedaluwarsa; jadwal tetap |
| Menunggu rekan / SPV | Jadwal terkait direvisi SPV | Dibatalkan sistem; alasan dan notifikasi tercatat |
| Disetujui | Permintaan pembatalan | Membuat proses pembatalan terpisah; hasil tukar tetap berlaku |

Status terminal tidak dapat diputuskan ulang. Penggantian rekan atau isi pertukaran membutuhkan pengajuan baru. Alasan pengajuan wajib. Form Batal sebelum dikirim hanya meninggalkan editor; berbeda dengan pembatalan pengajuan tersimpan.

### 6.5 Approval atomik dan konflik

Persetujuan SPV harus mengunci data terkait, memeriksa status, versi, tenggat, dan hasil jadwal, lalu menyimpan seluruh perubahan beserta audit dalam satu transaksi database. Jika satu langkah gagal, seluruh perubahan dibatalkan. Sistem tidak boleh menampilkan Disetujui ketika jadwal gagal berubah.

Dua request keputusan SPV yang datang bersamaan tidak menghasilkan dua keputusan. Pengulangan request tidak menggandakan perubahan. Konflik menampilkan informasi bahwa data sudah diperbarui dan pengguna perlu memuat data terbaru.

## 7. Pembatalan dan revisi manual

### 7.1 Sebelum pertukaran disetujui

Pengaju dapat membatalkan langsung sebelum batas H-1 dengan alasan. Persetujuan tambahan tidak diperlukan karena jadwal belum berubah. Rekan dapat menolak saat tahap menunggu rekan; setelah menerima, ia tidak dapat membatalkan pengajuan milik orang lain secara sepihak.

### 7.2 Setelah pertukaran disetujui

1. Salah satu dari dua pihak meminta pembatalan dengan alasan sebelum batas H-1 yang sama.
2. Pihak lainnya menerima atau menolak.
3. Jika diterima, SPV memutuskan sebelum batas.
4. Sistem memvalidasi kondisi pemulihan dan mengembalikan seluruh jadwal bersama jika disetujui.
5. Selama menunggu, ditolak, atau kedaluwarsa, jadwal hasil pertukaran tetap berlaku.

Status pembatalan: Menunggu Rekan, Menunggu SPV, Disetujui, Ditolak, Dibatalkan Peminta, Kedaluwarsa, dan Tidak Berlaku karena Perubahan Jadwal. Peminta boleh menarik permintaan yang belum selesai sebelum batas. Hanya satu pembatalan aktif per pertukaran.

Riwayat pertukaran tetap menunjukkan persetujuan awal beserta hasil pembatalannya; keputusan lama tidak dihapus. Setelah pembatalan berhasil, UI menampilkan Dibatalkan setelah persetujuan sebagai hasil akhir pertukaran.

### 7.3 Revisi oleh SPV

- Sebelum menyimpan revisi pada jadwal yang sedang diajukan, tampilkan daftar pengajuan yang akan dibatalkan dan minta konfirmasi tindakan.
- Revisi, pembatalan pengajuan terkait, audit, dan notifikasi disimpan dalam satu transaksi.
- Revisi terhadap hasil pertukaran yang sudah disetujui menjadi perubahan manual baru dengan alasan.
- Pembatalan lama tidak boleh mengembalikan snapshot di atas revisi baru. Permintaan pembatalan yang terdampak menjadi Tidak Berlaku dan kedua pihak diberi notifikasi.
- Jika kondisi telah berubah, pengguna perlu berkoordinasi melalui SPV; aplikasi tidak memulihkan jadwal lama secara diam-diam.

## 8. Akun dan administrasi

### 8.1 Identitas dan login

- Akun dibuat admin perusahaan, tidak ada registrasi publik.
- ID karyawan unik digunakan sebagai identitas login bersama password.
- Admin memberikan password sementara melalui proses pilot yang disepakati, bukan menyimpan password terbaca di database atau log.
- Pengguna wajib mengganti password sementara saat pertama masuk atau setelah reset, sebelum mengakses fitur operasional.
- Pengguna dapat mengganti password sendiri dengan verifikasi password saat ini.
- Reset dibantu admin; tidak mengirim email.
- Logout mengakhiri sesi. Login dibatasi laju percobaannya dan kesalahan tidak mengungkap apakah sebuah ID terdaftar.
- Akun admin pertama dibuat melalui perintah setup lokal yang terdokumentasi tanpa credential tetap di source code.

### 8.2 Penonaktifan

Admin dapat menonaktifkan karyawan. Akses dan sesi dicabut segera, pengajuan tertunda yang melibatkannya dibatalkan, dan riwayat dipertahankan. Jadwal mendatang ditandai perlu pengganti dan SPV mendapat notifikasi.

Karyawan nonaktif tidak dihitung sebagai personel tersedia. Akibatnya, jadwal yang sebelumnya valid dapat menjadi kurang personel; sistem harus menampilkan keadaan operasional ini secara jujur, bukan mempertahankan hitungan seolah petugas masih tersedia. Penonaktifan tetap dapat dilakukan untuk mencabut akses; perbaikan jadwal oleh SPV harus memenuhi aturan penyimpanan. Penggantian untuk shift yang sudah mulai belum didukung versi pertama dan perlu penanganan operasional di luar aplikasi.

Penonaktifan SPV mencabut haknya tanpa menghapus keputusan lama. Admin harus diberi peringatan jika outlet tidak lagi memiliki SPV aktif. Penonaktifan admin terakhir yang aktif ditolak agar akses administrasi tidak terkunci.

## 9. Notifikasi dan konfirmasi

### 9.1 Matriks peristiwa

| Peristiwa | Penerima | Tindakan tujuan |
| --- | --- | --- |
| Pertukaran diajukan | Rekan yang dipilih | Tinjau permintaan |
| Rekan menerima | SPV outlet; pengaju mendapat informasi status | Putuskan pengajuan |
| Rekan/SPV menolak | Kedua pihak terkait | Lihat keputusan dan alasan |
| Pertukaran disetujui | Kedua pihak; informasi perubahan untuk anggota aktif outlet | Lihat jadwal terbaru |
| Pengajuan batal/kedaluwarsa | Kedua pihak; SPV jika sudah masuk antreannya | Lihat status akhir |
| Pembatalan diminta | Pihak lainnya, kemudian SPV setelah diterima | Tinjau pembatalan |
| Pembatalan selesai | Kedua pihak; informasi perubahan jika pemulihan berhasil | Lihat hasil |
| Jadwal diterbitkan/direvisi | Anggota aktif outlet yang relevan | Lihat jadwal |
| Akun nonaktif meninggalkan jadwal | SPV outlet | Perbaiki kebutuhan personel |

Notifikasi umum tidak memuat alasan pribadi. Pengguna harus memiliki akses saat membuka tujuan notifikasi; tautan notifikasi tidak melewati pemeriksaan hak akses.

### 9.2 Perilaku

- Notifikasi tersimpan dalam database, diurutkan terbaru, dapat ditandai dibaca satu per satu atau semuanya.
- Dibaca tidak sama dengan konfirmasi perubahan jadwal.
- Setiap versi perubahan memuat daftar karyawan yang jadwal pribadinya terdampak; masing-masing memiliki konfirmasi terpisah dan waktu konfirmasi.
- Penerbitan awal juga meminta konfirmasi karyawan yang memperoleh jadwal.
- Konfirmasi selalu mengacu ke versi yang ditampilkan. Perubahan baru tidak boleh dianggap dikonfirmasi oleh tindakan pada versi lama.
- SPV melihat daftar belum konfirmasi, termasuk penanda jika shift terkait sudah mulai.
- Tidak ada sanksi atau catatan pelanggaran kehadiran otomatis.
- Jika perubahan berikutnya menggantikan versi lama, riwayat konfirmasi lama tetap tersedia dan UI mengutamakan versi yang masih berlaku.
- Versi pertama menggunakan polling ringan saat aplikasi aktif; tidak menjanjikan notifikasi ketika browser ditutup.

## 10. Kebutuhan layar dan interaksi

| Layar | Isi dan tindakan utama |
| --- | --- |
| Login | ID karyawan, password, pesan kesalahan, informasi hubungi admin untuk reset |
| Ganti password wajib | Password baru dan konfirmasi; akses fitur lain ditahan sampai selesai |
| Dashboard karyawan | Shift hari ini, jadwal terdekat, permintaan yang harus direspons, status pengajuan, konfirmasi tertunda |
| Dashboard SPV | Outlet yang ditugaskan, pengajuan menunggu keputusan, kekurangan personel, konfirmasi tertunda; tanpa pemilih outlet lain |
| Dashboard admin | Outlet dan akun aktif, penugasan SPV, konflik administrasi, ringkasan pengajuan tanpa alasan pribadi |
| Jadwal | Pemilih minggu, tampilan harian/mingguan, filter posisi/karyawan, penanda jadwal sendiri dan kebutuhan pengganti |
| Editor jadwal SPV | Grid satu minggu, Pagi/Sore/Libur, hitungan per posisi/shift, validasi, alasan revisi, Simpan Jadwal |
| Ajukan pertukaran | Pilih penugasan sendiri, rekan eligible, tanggal/penugasan balasan, alasan, perbandingan sebelum-sesudah, tenggat |
| Permintaan untuk saya | Antrean sebagai rekan, detail perbandingan, setuju/tolak beserta alasan penolakan |
| Persetujuan SPV | Antrean outlet, bukti persetujuan rekan, hasil validasi, keputusan dan alasan penolakan |
| Detail pengajuan | Pihak, outlet, posisi, jadwal, alasan sesuai izin, timeline, tenggat, tindakan sesuai status |
| Pembatalan | Alasan, preview pemulihan, status persetujuan rekan/SPV |
| Notifikasi | Belum dibaca/semua, penanda dibaca, tautan tujuan yang sesuai akses |
| Konfirmasi jadwal | Perubahan sebelum-sesudah dan tombol Saya sudah melihat jadwal terbaru |
| Riwayat | Filter rentang tanggal, outlet sesuai izin, status, ID; detail audit sesuai hak akses |
| Admin outlet/posisi | Tambah, edit, aktif/nonaktif dengan pemeriksaan dependensi |
| Admin akun | Tambah/edit akun, role, posisi, status, reset password |
| Keanggotaan outlet | Outlet tetap, posisi, tanggal bergabung; dikelola melalui akun karyawan |
| Penugasan SPV | Hubungan SPV dan outlet yang dapat dikelola |
| Profil | Identitas akun, penempatan, posisi, ganti password, logout |

### 10.1 Standar interaksi

- Pertahankan identitas visual cokelat/kuning prototype tanpa menjadikan warna satu-satunya penanda status.
- HP menggunakan navigasi yang dapat dibuka/tutup; sidebar tetap 200–260 px prototype tidak dipaksakan pada layar sempit.
- Tabel boleh scroll horizontal di area tabel; kontrol penting tidak terpotong.
- Label input terhubung secara semantik; semua tindakan dapat dijangkau keyboard, fokus terlihat, dan modal mengelola fokus serta mengembalikannya setelah ditutup.
- Status memuat teks, bukan hanya warna atau ikon.
- Form menampilkan loading, validasi per field, error umum, dan mempertahankan input saat gagal.
- Cegah submit berulang di UI dan server. Setelah data berubah, perbarui tampilan dari hasil server.
- Empty state dibedakan: belum ada jadwal, tidak ada pengajuan, hasil filter kosong, atau akses outlet belum diberikan.
- Saat jaringan/server gagal, jangan mengklaim data tersimpan; sediakan coba lagi tanpa menduplikasi tindakan.
- Perubahan belum disimpan memunculkan peringatan sebelum meninggalkan editor.
- Tampilkan zona waktu pada tenggat dan detail waktu yang menentukan keputusan.

## 11. Katalog kebutuhan fungsional

| ID | Kebutuhan | Prioritas |
| --- | --- | --- |
| FR-01 | Autentikasi, ganti/reset password, pencabutan sesi | Wajib |
| FR-02 | Otorisasi role dan outlet di server | Wajib |
| FR-03 | Master outlet, posisi, akun, penugasan SPV | Wajib |
| FR-04 | Keanggotaan outlet tetap dan tanggal bergabung karyawan | Wajib |
| FR-05 | Editor mingguan dan penyimpanan atomik | Wajib |
| FR-06 | Validasi satu shift/hari, dua libur/minggu, komposisi 1 Kasir + 2 Baker | Wajib |
| FR-07 | Pengajuan pertukaran eligible dengan preview hasil | Wajib |
| FR-08 | Respons rekan lalu keputusan SPV | Wajib |
| FR-09 | Batas H-1 dan kedaluwarsa berdasarkan server | Wajib |
| FR-10 | Pembatalan sebelum/sesudah persetujuan | Wajib |
| FR-11 | Revisi manual dengan pemeriksaan dependensi dan audit | Wajib |
| FR-12 | Notifikasi persisten dalam aplikasi | Wajib |
| FR-13 | Konfirmasi per versi perubahan dan monitoring SPV | Wajib |
| FR-14 | Riwayat sebelum-sesudah yang tidak dapat diedit pengguna | Wajib |
| FR-15 | Dashboard, filter, serta penanganan loading/empty/error | Wajib |
| FR-16 | Penonaktifan akun dan penanda kebutuhan pengganti | Wajib |

## 12. Model data konseptual

Model berikut adalah rancangan implementasi; nama tabel final dapat mengikuti konvensi Laravel. Tidak menggunakan nama karyawan sebagai kunci relasi.

| Entitas | Data penting dan hubungan |
| --- | --- |
| User | ID internal, ID karyawan unik, nama, role, password hash, aktif, wajib ganti password |
| Position | Kode/nama unik, aktif; Kasir dan Baker sebagai data awal |
| Outlet | Kode, nama, timezone, aktif |
| EmployeeMembership | Karyawan unik, outlet tetap, posisi, tanggal bergabung; tidak ada proses mutasi outlet |
| SupervisorAssignment | SPV dan outlet; satu-ke-satu untuk penugasan aktif, keduanya unik |
| ShiftType | Pagi/Sore beserta jam; seragam lintas outlet |
| ScheduleWeek | Outlet, tanggal Senin, nomor versi, pembuat/pengubah |
| ScheduleDay | Karyawan, tanggal, outlet/posisi saat penugasan, kerja/libur, shift, versi |
| SwapRequest | Pengaju, rekan, outlet, alasan, status, tenggat, respons rekan, keputusan SPV |
| SwapAffectedDay | Semua hari yang terlibat; snapshot sebelum/sesudah serta versi acuan, termasuk hari libur |
| CancellationRequest | Pertukaran, peminta, alasan, status, respons pihak lain, keputusan SPV, tenggat |
| ScheduleChange | Jenis perubahan, referensi pengajuan, pelaku, alasan, waktu, versi |
| ScheduleChangeDetail | Hari terdampak dan nilai sebelum/sesudah |
| Notification | Penerima, peristiwa, referensi target, waktu, dibaca pada |
| Acknowledgement | Perubahan/versi, pengguna terdampak, waktu konfirmasi |
| AuditEvent | Pelaku, tindakan, objek, waktu dan metadata aman; tidak memuat password |

Constraint penting:

- ID karyawan unik; hari jadwal unik per karyawan dan tanggal.
- Minggu unik per outlet dan tanggal mulai.
- Konfirmasi unik per pengguna dan versi perubahan.
- Foreign key menjaga referensi; riwayat tidak cascade-delete ketika akun dinonaktifkan.
- Snapshot menyimpan konteks historis sehingga perubahan nama posisi atau penempatan tidak mengubah arti audit.
- Penguncian hari yang sedang diajukan harus mencakup seluruh sel terdampak, tidak hanya dua shift kerja.
- Waktu peristiwa disimpan secara konsisten, misalnya UTC; tanggal jadwal dan perhitungan deadline memakai timezone outlet.

## 13. Arsitektur dan lingkungan lokal

### 13.1 Stack disepakati

- Frontend: React + TypeScript.
- Backend: Laravel.
- Penghubung halaman dan aksi: Inertia.
- Database: MySQL.
- Satu repository dan satu aplikasi; API terpisah tidak diperlukan untuk versi pertama.
- Styling mengikuti identitas prototype; utility CSS dapat mengikuti starter kit tanpa menambahkan UI framework lain yang tidak diperlukan.

Versi package dipilih dari rilis stabil yang kompatibel saat setup dan dikunci dalam lockfile. PRD tidak mengunci nomor versi yang belum diperiksa pada lingkungan pengembangan.

### 13.2 Pembagian tanggung jawab

React mengelola rendering, form, preview, dan state interaksi. Laravel menjadi sumber kebenaran untuk akses, status proses, deadline, validasi hasil jadwal, serta penyimpanan. Validasi frontend membantu pengguna tetapi tidak menggantikan validasi server.

Policies/authorization membatasi role dan outlet. Layanan domain menangani jadwal, pertukaran, pembatalan, dan penempatan. Transaksi database dan penguncian baris mencegah keputusan bersamaan menimpa satu sama lain. Versi jadwal mendeteksi editor yang memakai data lama.

Kedaluwarsa diperiksa pada pembacaan/tindakan terkait dan melalui scheduler lokal. Ketepatan aturan tidak boleh bergantung pada scheduler atau tab browser yang terus terbuka. Notifikasi dan audit dicatat sebagai bagian dari transaksi; tidak diperlukan layanan pesan eksternal.

### 13.3 Operasi lokal

- Setup mencakup dependency, konfigurasi `.env`, database, migration, data simulasi, akun awal, build frontend, dan menjalankan scheduler.
- `.env.example` hanya berisi placeholder. `.env`, credential, dump data nyata, dan log sensitif tidak masuk Git.
- Data dan perubahan harus bertahan setelah browser maupun server di-restart.
- Awalnya browser di komputer lokal dapat digunakan dengan data simulasi. Akses dari HP memerlukan server pada jaringan lokal tepercaya dan konfigurasi akses yang sesuai; `localhost` HP bukan komputer pengembang.
- Pilot dengan credential/data nyata membutuhkan transport yang melindungi sesi, bukan mengirim login melalui jaringan yang tidak terlindungi.
- Siapkan langkah backup dan restore database lokal sebelum memakai data pilot nyata. Lakukan verifikasi pemulihan, bukan hanya membuat file backup.
- Tidak ada tindakan deploy publik dalam scope ini.

## 14. Keamanan dan kebutuhan nonfungsional

### 14.1 Keamanan

- Semua query dan mutasi terlindungi memeriksa identitas, role, status aktif, serta cakupan outlet pada server.
- Hash password adaptif menggunakan fasilitas framework; tidak ada password terbaca, password demo tetap, atau credential di source/log.
- Gunakan perlindungan CSRF, cookie sesi yang sesuai lingkungan, validasi request, dan query terparameterisasi.
- Render alasan/nama sebagai teks, tanpa interpolasi HTML mentah dari input. Pola `innerHTML` prototype tidak dibawa untuk konten pengguna.
- Batasi field yang boleh diperbarui agar pengguna tidak dapat mengubah role atau outlet melalui manipulasi payload.
- Catat keputusan penting tanpa merekam password, token sesi, atau payload sensitif secara berlebihan.
- Nonaktif, reset password, dan perubahan hak akses harus memengaruhi sesi lama secara nyata.
- Riwayat tidak memiliki fitur ubah/hapus bagi role aplikasi. Retensi dan penghapusan data administratif di luar aplikasi belum ditetapkan untuk produksi.

Risiko yang harus ditutup sebelum pilot nyata:

| Risiko | Severity jika terbawa ke aplikasi nyata | Dampak |
| --- | --- | --- |
| Login/role hanya simulasi seperti prototype | Kritis | Pengguna bisa bertindak sebagai admin/SPV tanpa otorisasi |
| Keputusan tanpa transaksi dan pemeriksaan versi | Tinggi | Jadwal berubah sebagian atau keputusan tidak sesuai jadwal |
| Input pengguna dimasukkan sebagai HTML mentah | Tinggi | Potensi eksekusi script dan penyalahgunaan sesi |
| Tidak membatasi outlet di server | Tinggi | Kebocoran data dan perubahan jadwal outlet lain |
| Notifikasi hanya di aplikasi | Keterbatasan operasional | Pengguna yang tidak membuka aplikasi dapat terlambat merespons |

### 14.2 Usability dan kompatibilitas

- Target verifikasi awal: browser desktop dan mobile pada viewport 360, 390, dan 1366 px.
- Browser/perangkat nyata yang tersedia dicatat saat pengujian. Safari iOS tidak boleh diklaim didukung berdasarkan simulasi ukuran layar saja.
- Loading, kosong, error, konflik versi, sesi habis, dan akses ditolak memiliki pesan serta tindakan pemulihan yang jelas.
- Jadwal yang belum tersimpan harus dibedakan dari jadwal resmi tersimpan.

### 14.3 Performa

Target rancangan lokal: p95 request baca utama selesai dalam 3 detik dengan data uji yang dicatat. Ini target awal, bukan hasil ukur. Profil beban, jumlah pengguna bersamaan, perangkat server, dan jaringan disesuaikan jumlah pengguna pilot yang belum diketahui. Tidak mewarisi angka 16 pengguna dari SRS broadcast sebagai fakta kapasitas Roti’O.

## 15. Acceptance criteria dan rencana pengujian

Semua hasil pada bagian ini **belum diuji**. Skenario dijalankan terhadap implementasi, dengan waktu server terkendali untuk kasus deadline.

| ID | Referensi | Skenario dan hasil yang diharapkan |
| --- | --- | --- |
| AC-01 | FR-01 | Login valid berhasil; password salah ditolak; password sementara memaksa penggantian |
| AC-02 | FR-01,02,16 | Akun dinonaktifkan saat sesi terbuka tidak dapat membaca atau melakukan tindakan terlindungi lagi |
| AC-03 | FR-02 | Karyawan/admin tidak dapat memutuskan pengajuan dengan request langsung; SPV outlet A tidak dapat memutuskan milik B |
| AC-04 | FR-03,04 | Admin membuat outlet, akun, dan penugasan SPV; SPV kedua pada outlet yang sama atau outlet kedua bagi SPV yang sama ditolak |
| AC-05 | FR-02,04 | Perubahan outlet karyawan terdaftar ditolak, termasuk request langsung dari admin; SPV tidak bisa mengakses outlet lain |
| AC-06 | FR-05,06 | Minggu valid tersimpan seluruhnya; satu sel invalid menyebabkan seluruh penyimpanan ditolak |
| AC-07 | FR-06 | Shift berisi 3 orang tetapi tanpa Kasir, atau hanya 1 Baker, ditolak |
| AC-08 | FR-06 | Dua shift dalam sehari, sel kosong, dan jumlah libur bukan dua pada minggu penuh ditolak |
| AC-09 | FR-07 | Rekan beda outlet, beda posisi, nonaktif, atau penempatan tidak cocok tidak eligible dan tetap ditolak jika ID dikirim manual |
| AC-10 | FR-07,08 | Pertukaran tanggal sama dan contoh kerja-libur beda tanggal berhasil setelah dua tahap persetujuan |
| AC-11 | FR-06,07 | Pertukaran lintas minggu yang mengubah jumlah libur menjadi 1 dan 3 ditolak; yang mempertahankan aturan dapat diterima |
| AC-12 | FR-08 | SPV tidak dapat menyetujui sebelum rekan menerima; penolakan menyimpan alasan dan mempertahankan jadwal |
| AC-13 | FR-09 | Pada H-1 23.59.59 tindakan valid diterima; pada hari shift pertama 00.00.00 ditolak sesuai timezone outlet |
| AC-14 | FR-09 | Pengajuan kedaluwarsa tetap ditolak walau scheduler mati atau browser menampilkan status lama |
| AC-15 | FR-07,08 | Pengajuan kedua atas salah satu hari yang sudah terlibat ditolak, termasuk request bersamaan |
| AC-16 | FR-08 | Dua request keputusan bersamaan dari SPV yang berwenang menghasilkan tepat satu keputusan dan satu perubahan jadwal |
| AC-17 | FR-08 | Kegagalan database di tengah approval tidak menyimpan perubahan sebagian, status Disetujui, atau notifikasi sukses |
| AC-18 | FR-10 | Pembatalan pengajuan tertunda langsung terminal tanpa perubahan jadwal |
| AC-19 | FR-10 | Pembatalan hasil tukar perlu rekan lalu SPV; hanya saat selesai jadwal dipulihkan seluruhnya |
| AC-20 | FR-10 | Pembatalan ditolak/kedaluwarsa mempertahankan jadwal hasil tukar |
| AC-21 | FR-11 | Revisi jadwal tertunda memberi peringatan, membatalkan pengajuan terkait, dan membuat audit/notifikasi dalam satu transaksi |
| AC-22 | FR-11 | Revisi manual terbaru tidak dapat ditimpa pembatalan pertukaran lama |
| AC-23 | FR-11 | SPV dapat merevisi shift hari ini yang belum mulai dengan alasan; shift mulai/lewat tidak dapat diedit |
| AC-24 | FR-12,13 | Menandai notifikasi dibaca tidak menandai konfirmasi; konfirmasi versi lama tidak berlaku pada revisi baru |
| AC-25 | FR-12,13 | Penerima dan SPV melihat informasi yang tepat; alasan pribadi tidak muncul pada notifikasi umum/admin |
| AC-26 | FR-14 | Audit memuat sebelum-sesudah, alasan, pelaku, waktu; tidak dapat diubah atau dihapus lewat role aplikasi |
| AC-27 | FR-16 | Penonaktifan membatalkan pengajuan tertunda dan menandai jadwal mendatang perlu pengganti tanpa menghapus riwayat |
| AC-28 | FR-15 | Alur utama bisa digunakan dengan keyboard dan viewport target; modal, error, loading, serta empty state berfungsi |
| AC-29 | FR-01,02 | Input menyerupai HTML tampil sebagai teks; request CSRF invalid ditolak dan field role tak sah tidak berubah |
| AC-30 | FR-05,08 | Refresh/restart mempertahankan data; submit ulang tidak menggandakan pengajuan/keputusan |
| AC-31 | FR-05 | Dua editor mingguan menggunakan versi lama; penyimpan kedua mendapat konflik tanpa menimpa perubahan pertama |
| AC-32 | Lokal | Backup dapat dipulihkan pada database uji dan jadwal, akun, serta riwayat tetap konsisten |
| AC-33 | FR-04,06 | Karyawan mulai Jumat/Sabtu/Minggu bekerja masing-masing 3/2/1 hari tanpa libur sampai akhir minggu; hari sebelum bergabung ditandai belum bergabung. Minggu berikutnya wajib dua hari libur, dan kerja Jumat–Selasa tanpa jeda ditolak karena lima hari berturut-turut |
| AC-34 | FR-04,06 | Untuk tanggal bergabung Senin–Kamis, dua hari libur dihitung hanya sejak bergabung; hari sebelumnya tidak dihitung sebagai libur dan minggu pertama tidak diwajibkan lima hari kerja |
| AC-35 | FR-06,07,10,11 | Penyusunan, revisi, pertukaran, dan pemulihan pembatalan yang menghasilkan lima hari kerja berturut-turut ditolak, termasuk lintas minggu |
| AC-36 | FR-02,06 | SPV tidak dapat ditambahkan sebagai Kasir/Baker pada jadwal atau dihitung untuk memenuhi minimum personel |

Strategi uji:

- Unit test untuk batas waktu, eligibility, komposisi, dan hasil pertukaran/pemulihan.
- Integration test untuk policies, transaksi, locking, sesi nonaktif, serta alur lintas entitas.
- Uji end-to-end untuk alur pengguna karyawan, rekan, SPV, dan admin.
- Uji manual pada perangkat tersedia untuk responsive layout, keyboard, dan keterbacaan.
- UAT oleh perwakilan SPV, admin perusahaan, dan tim proyek; dimulai dengan data simulasi.

## 16. Pemetaan prototype ke implementasi

| Bagian prototype | Tindakan lanjutan |
| --- | --- |
| Identitas visual dan menu | Pertahankan arah visual, buat komponen React dan navigasi sesuai role |
| Login bebas dan pergantian role lewat profil | Ganti dengan autentikasi dan otorisasi nyata; hapus pergantian role demo |
| Identitas pengguna selalu Ibnu | Ganti dengan pengguna sesi aktif |
| Jadwal satu minggu hardcoded | Jadwal berbasis tanggal/outlet/database, editor mingguan dan validasi |
| Tukar hanya tanggal sama dan bukan libur | Tambahkan pilihan beda tanggal, hari libur, serta preview seluruh hari terdampak |
| Label rekan Disetujui hardcoded | Tambahkan tahap respons rekan sebenarnya |
| Status Disetujui sebelum memastikan mutasi berhasil | Ganti transaksi atomik dan validasi versi |
| Notifikasi global dalam array | Notifikasi per penerima tersimpan, terpisah dari konfirmasi |
| Riwayat dan angka tambahan dummy | Ambil data nyata sesuai akses dan filter |
| CRUD pengguna tidak terhubung jadwal | Hubungkan akun, posisi, penempatan, dan eligibility |
| Sidebar desktop pada layar sempit | Navigasi mobile yang tidak menutup kontrol utama |
| ERD broadcast | Hapus modul broadcast; tambahkan outlet, posisi, penempatan, dan pembatalan dua tahap |

## 17. Tahapan implementasi dan definisi selesai

| Tahap | Hasil yang dapat diperiksa |
| --- | --- |
| 1. Fondasi | Setup lokal, migration, autentikasi, role/outlet policies, seed data simulasi |
| 2. Administrasi | Outlet, posisi, akun, penugasan SPV, keanggotaan outlet dan tanggal bergabung |
| 3. Jadwal | Editor mingguan, validasi kapasitas/personel/libur, versi dan audit |
| 4. Pertukaran | Eligibility, preview, respons rekan, keputusan SPV, batas waktu dan transaksi |
| 5. Pembatalan/revisi | Pemulihan dua tahap, konflik revisi, penonaktifan dan kebutuhan pengganti |
| 6. Informasi pengguna | Notifikasi, konfirmasi per versi, dashboard dan riwayat |
| 7. Verifikasi/pilot | Pengujian otomatis/manual, backup-restore, panduan, serta evaluasi bersama pengguna |

Deadline kalender belum ditentukan. Tahap ini menunjukkan dependensi pengerjaan, bukan komitmen tanggal.

Versi lokal dinyatakan siap pilot jika kebutuhan wajib selesai, acceptance criteria relevan lulus dengan bukti, tidak ada risiko akses/integritas data tinggi yang terbuka, lingkungan lokal dapat dijalankan ulang dari panduan, dan keputusan operasional yang memengaruhi pilot telah divalidasi.

Penggunaan sebagai jadwal resmi outlet memerlukan persetujuan pihak outlet/perusahaan setelah evaluasi. Proses ini tidak sama dengan menyelesaikan build lokal.

## 18. Keputusan terbuka dan batas validasi

| ID | Hal yang belum ditetapkan | Perlakuan dalam versi lokal |
| --- | --- | --- |
| OQ-03 | Aturan hari libur pada minggu penonaktifan | Diputuskan 27 November 2026: pertahankan riwayat dan keluarkan karyawan dari perhitungan sejak nonaktif |
| OQ-05 | Jumlah karyawan dan pengguna bersamaan aktual pilot | Gunakan seed simulasi yang memenuhi kapasitas; target beban final menunggu jumlah aktual |
| OQ-06 | Browser/perangkat uji, tanggal target selesai, dan mesin server lokal | Catat sebelum pelaksanaan pengujian; tidak klaim perangkat yang belum diuji |
| OQ-07 | Pengelola backup, retensi data, dan pemulihan saat pilot | Sediakan mekanisme lokal; penanggung jawab dan kebijakan final ditentukan bersama perusahaan |

Keputusan yang sudah disepakati: React dengan Laravel/Inertia dan MySQL; Markdown sebagai format PRD; lokal dahulu; dua hari libur mingguan; posisi Kasir/Baker dan minimum 1+2 per shift; seluruh aturan persetujuan, pembatalan, dan akses sebagaimana tertulis di atas.

Pembaruan versi 1.3 menggantikan pembahasan mutasi sebelumnya: tidak ada perpindahan outlet; satu outlet memiliki satu SPV yang hanya dapat mengakses outlet tersebut. SPV tidak dijadwalkan sebagai Kasir/Baker. Maksimal empat hari kerja berturut-turut, outlet buka tujuh hari, dan karyawan baru yang bergabung Senin–Kamis tetap mendapat dua hari libur pada sisa minggu pertama; yang mulai Jumat–Minggu belum mendapat libur hingga minggu pertama berakhir, lalu mengikuti dua hari libur per minggu. OQ-01, OQ-02, dan OQ-04 sudah selesai diputuskan.

Perbedaan atau koreksi narasumber setelah dokumen ini dibuat harus dicatat sebagai perubahan keputusan, lalu diperiksa dampaknya terhadap model data, UI, aturan bisnis, dan acceptance criteria. Jangan mengubah aturan diam-diam hanya agar data contoh dapat disimpan.
