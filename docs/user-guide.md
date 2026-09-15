# Panduan Pengguna Guestory

Panduan ini ditujukan kepada pengguna nonteknis Guestory pada aplikasi web versi `main` per 15 September 2026. Istilah tombol dan menu ditulis sama seperti yang tampil di antarmuka. Beberapa label masih menggunakan bahasa Inggris.

> **Penting:** selalu periksa **Current event** di bagian atas sidebar sebelum mengubah data, mengirim undangan, memoderasi foto, atau melakukan ekspor. Hampir semua data dan paket di Guestory berlaku per event.

## Daftar isi

- [1. Mengenal peran dan akses](#1-mengenal-peran-dan-akses)
- [2. Memulai, aktivasi, login, dan reset password](#2-memulai-aktivasi-login-dan-reset-password)
- [3. Navigasi Admin CMS](#3-navigasi-admin-cms)
- [4. Panduan SUPERADMIN](#4-panduan-superadmin)
- [5. Panduan Admin Event atau EVENT_OWNER](#5-panduan-admin-event-atau-event_owner)
- [6. Panduan Penerima Tamu atau Receiver](#6-panduan-penerima-tamu-atau-receiver)
- [7. Perjalanan tamu](#7-perjalanan-tamu)
- [8. Status dan istilah](#8-status-dan-istilah)
- [9. Pemecahan masalah](#9-pemecahan-masalah)
- [10. Keamanan dan privasi](#10-keamanan-dan-privasi)
- [11. Checklist operasional event](#11-checklist-operasional-event)
- [12. Batasan antarmuka saat ini](#12-batasan-antarmuka-saat-ini)
- [13. Dokumentasi terkait](#13-dokumentasi-terkait)

## 1. Mengenal peran dan akses

### SUPERADMIN

SUPERADMIN mengelola akun Event Owner melalui halaman **Users**. SUPERADMIN juga dapat membuka **Event CMS** dan mengelola event yang dibuat oleh akun SUPERADMIN itu sendiri.

Batas penting:

- SUPERADMIN **tidak otomatis dapat melihat atau mengubah event milik Event Owner lain**.
- Daftar event tetap dipisahkan berdasarkan pemiliknya.
- SUPERADMIN dapat membuat Event Owner, mengirim ulang aktivasi yang masih tertunda, menangguhkan, dan mengaktifkan kembali Event Owner.
- SUPERADMIN tidak dapat mengubah status akun SUPERADMIN lain dari layar **Users**.

### Admin Event / EVENT_OWNER

Event Owner mengelola event miliknya sendiri: event, tamu, desain undangan, petugas, kehadiran, foto, WhatsApp, profil, serta paket dan kuota. Ia tidak dapat membuka event milik akun lain.

### Penerima Tamu / Staff / Receiver

Dalam antarmuka, peran ini disebut **Receiver** atau **Petugas event**. Receiver hanya dapat:

- melihat event dengan penugasan **ACTIVE**;
- memvalidasi dan mengonfirmasi check-in;
- mencari tamu untuk check-in manual; dan
- melihat check-in terbaru pada event yang sedang dipilih.

Receiver tidak dapat mengelola tamu, undangan, foto, billing, atau konfigurasi event.

### Tamu

Tamu tidak memerlukan akun dan tidak perlu memasang aplikasi. Tamu menggunakan tautan undangan personal untuk melihat detail event, memberikan RSVP, menampilkan QR pribadi, melihat album yang disetujui, dan mengunggah foto bila fitur tersebut tersedia.

## 2. Memulai, aktivasi, login, dan reset password

### 2.1 Membuat akun Event Owner sendiri

1. Buka halaman **Admin** dari beranda, lalu pilih **Daftar sebagai event owner**.
2. Pada halaman **Buat akun Guestory.**, isi **Nama**, **Email**, **Password**, dan **Konfirmasi Password**.
3. Password harus minimal 8 karakter serta mengandung huruf dan angka.
4. Centang **Saya menyetujui syarat layanan.** dan **Saya menyetujui kebijakan privasi.**
5. Pilih **Daftar**.
6. Buka email dari Guestory dan ikuti tautan verifikasi.
7. Pada halaman **Verifikasi email.**, pilih **Verifikasi Email**.
8. Setelah pesan berhasil muncul, pilih **Login Guestory**.

Tautan verifikasi berlaku 30 menit dan hanya dapat dipakai satu kali. Antarmuka saat ini belum menyediakan tombol kirim ulang verifikasi; bila tautan kedaluwarsa, hubungi pengelola Guestory.

### 2.2 Mengaktifkan akun yang dibuat SUPERADMIN atau Admin Event

Alur ini berlaku untuk Event Owner yang diprovisikan SUPERADMIN dan Receiver baru yang ditambahkan Admin Event.

1. Buka email aktivasi Guestory.
2. Ikuti tautan menuju halaman **Aktifkan akun.**
3. Isi **Password** dan **Konfirmasi Password**. Gunakan minimal 8 karakter dengan huruf dan angka.
4. Pilih **Aktifkan Akun**.
5. Setelah berhasil, pilih **Login Guestory**.
6. Event Owner masuk melalui halaman **Admin**; Receiver masuk melalui halaman **Receiver**.

Tautan aktivasi berlaku 60 menit dan hanya dapat dipakai satu kali. Bila kedaluwarsa, minta SUPERADMIN memilih **Resend** untuk Event Owner, atau minta Admin Event memilih **Resend aktivasi** untuk Receiver.

### 2.3 Login Admin Event atau SUPERADMIN

1. Buka halaman **Admin**.
2. Isi **Email** dan **Password**.
3. Pilih **Login Admin**.
4. Pengguna `EVENT_OWNER` dan `SUPERADMIN` akan masuk ke Admin CMS.

Untuk langsung mengelola pengguna sebagai SUPERADMIN, buka halaman **Users** SUPERADMIN dan login pada formulir **Kelola akun owner.**

### 2.4 Login Receiver

1. Buka halaman **Receiver** dari beranda, atau gunakan **Open Receiver App** di bagian bawah sidebar admin.
2. Isi email akun Receiver dan password yang dibuat saat aktivasi.
3. Pilih **Login Receiver**.

Halaman Receiver hanya menerima akun dengan role `RECEIVER`. Akun Event Owner atau SUPERADMIN yang juga ditugaskan sebagai petugas dapat memiliki izin receiver di sisi sistem, tetapi halaman login Receiver saat ini menolak role selain `RECEIVER`; lihat [batasan antarmuka](#12-batasan-antarmuka-saat-ini).

### 2.5 Lupa atau reset password

1. Pada halaman login Admin atau Receiver, pilih **Lupa password?**
2. Isi **Email** pada halaman **Reset akses admin.**
3. Pilih **Kirim Link Reset**.
4. Guestory selalu menampilkan jawaban umum demi keamanan. Jika akun aktif dan email terdaftar, email reset akan dikirim.
5. Buka tautan dalam email, lalu isi **Email**, **Password Baru**, dan **Konfirmasi Password**.
6. Pilih **Simpan Password**. Setelah berhasil, Anda diarahkan ke login Admin.

Tautan reset berlaku 60 menit. Reset password mengakhiri sesi lama, sehingga perangkat lain perlu login kembali. Receiver yang selesai reset dapat kembali membuka halaman **Receiver** untuk login.

### 2.6 Logout

Pilih **Logout** pada halaman yang sedang digunakan. Jangan hanya menutup tab, terutama pada perangkat bersama. Bila tidak menemukan tombol Logout pada modul tertentu, kembali ke **Overview**, **Attendance**, **Photos**, atau **WhatsApp**, lalu pilih **Logout**.

## 3. Navigasi Admin CMS

Pada desktop, menu berada di sidebar kiri. Pada layar kecil, pilih **Menu** untuk membukanya dan tombol **Tutup navigasi** untuk menutupnya.

### Pemilih event global

Di bawah logo Guestory terdapat **Current event**. Pilihan ini berlaku lintas halaman Admin dan disimpan di browser.

1. Buka **Current event**.
2. Pilih event yang akan dikerjakan.
3. Pastikan nama event yang tampil tebal sudah benar.
4. Setelah berpindah halaman, periksa lagi nama event sebelum tindakan penting.

Jika pilihan lama tidak lagi dapat diakses, Guestory memilih event pertama yang tersedia. Jika tertulis **No event selected**, buat event atau pastikan akun memang memiliki event.

### Menu Workspace

- **Overview** — ringkasan metrik, lima tamu pertama, tiga event terbaru, status event, dan aktivitas check-in.
- **Events** — daftar lengkap event serta kontrol **Create Event**, **Publish**, dan **Archive**.
- **Guests** — daftar lengkap tamu, pencarian, tambah/edit/hapus, undangan, QR, dan SVG.
- **Invitation Builder** — tema, susunan bagian, teks, gambar slideshow, dan pratinjau.
- **Attendance** — daftar status kehadiran, buku tamu digital, filter, dan ekspor attendance.
- **Receivers** — penugasan dan pencabutan akses petugas.
- **Photos** — moderasi foto tamu.

### Menu Operations

- **WhatsApp** — kirim ke satu tamu atau secara massal dan lihat log pengiriman.
- **Plan & Billing** — paket dan kuota per event.
- **Settings** — nama akun dan nomor WhatsApp pengirim/akun.

Di bagian bawah sidebar, **Open Receiver App** membuka halaman Receiver.

## 4. Panduan SUPERADMIN

### 4.1 Membuka User management

1. Buka halaman **Users** SUPERADMIN.
2. Login menggunakan akun SUPERADMIN.
3. Halaman **User management** menampilkan daftar **Event owners**, role, status, jumlah event, dan tindakan yang tersedia.
4. Pilih **Refresh** bila ingin mengambil daftar terbaru.

### 4.2 Membuat Event Owner

1. Di panel **Provision owner**, isi **Nama** dan **Email**.
2. Pilih **Kirim aktivasi**.
3. Pastikan muncul pesan bahwa owner dibuat dan link aktivasi dikirim.
4. Minta calon owner mengikuti email aktivasi dan membuat password sendiri.

SUPERADMIN tidak membuat atau melihat password owner. Satu email hanya dapat digunakan oleh satu akun.

### 4.3 Mengirim ulang aktivasi

Untuk akun berstatus `PENDING_ACTIVATION`, pilih **Resend** pada baris akun. Tautan baru dikirim dan tautan aktivasi lama tidak lagi menjadi rujukan yang aman untuk dibagikan.

### 4.4 Menangguhkan dan mengaktifkan kembali Event Owner

- Untuk akun `ACTIVE`, pilih **Suspend**. Sesi aktif owner langsung dicabut.
- Untuk akun selain `ACTIVE`, tombol yang terlihat adalah **Activate**. Gunakan ini untuk mengaktifkan kembali akun yang sebelumnya `SUSPENDED`.

Catatan: pada akun `PENDING_ACTIVATION`, tombol **Activate** juga tampil. Jangan gunakan tombol itu sebagai pengganti aktivasi email karena akun belum mempunyai password. Gunakan **Resend** agar pemilik membuat password melalui alur yang benar.

### 4.5 Mengelola event milik SUPERADMIN sendiri

1. Dari sidebar SUPERADMIN pilih **Event CMS**.
2. Gunakan menu Admin CMS seperti Event Owner.
3. Hanya event dengan pemilik akun SUPERADMIN tersebut yang akan terlihat.

SUPERADMIN tidak memiliki layar untuk mengambil alih, membuka, atau mengedit event owner lain.

## 5. Panduan Admin Event atau EVENT_OWNER

### 5.1 Overview dan metrik

Buka **Workspace → Overview**. Bagian atas menampilkan:

- **Total Guests** — jumlah record/entri tamu, bukan selalu jumlah orang;
- **Confirmed** — jumlah orang pada undangan dengan RSVP hadir;
- **Checked In** — jumlah orang aktual yang telah dicatat hadir; dan
- **Photos** — seluruh foto event, termasuk yang belum disetujui.

Panel lain memperlihatkan **Attendance Rate**, **Recent Check-ins**, event terbaru, dan tautan cepat. Gunakan **Refresh** untuk mengambil data terbaru.

### 5.2 Membuat dan mengubah status event

#### Membuat event

1. Pilih **Workspace → Events** atau gunakan panel **Event Management** di Overview.
2. Pilih **Create Event**.
3. Isi **Nama event**, **Tanggal**, **Jenis**, dan bila ada **Venue (opsional)**.
4. Pilih **Buat Event Draft**.
5. Event baru menjadi **Current event** dan berstatus `Draft`.

Pilihan jenis yang tersedia: `Wedding`, `Birthday`, `Engagement`, `Corporate`, `Gathering`, dan `Other`.

#### Publish

1. Pilih event yang benar pada **Current event**.
2. Di panel **Event Management**, pilih **Publish**.
3. Tunggu pesan **Event berhasil dipublish.**

Event `Published` dapat dilayani oleh undangan publik dan validasi QR. Desain undangan perlu disimpan terpisah melalui **Save & publish design**.

#### Archive

1. Pilih event pada **Current event**.
2. Pilih **Archive**.
3. Tunggu pesan **Event berhasil diarsipkan.**

Event `Archived` tidak tersedia sebagai undangan publik aktif dan QR-nya tidak boleh digunakan untuk check-in. Mengarsipkan event tidak menghapus datanya.

#### Edit dan hapus event

Backend Guestory mendukung edit dan hapus event, tetapi deployed UI `main` saat ini **belum menampilkan tombol atau formulir Edit/Delete event**. Jangan mencari atau mengandalkan tombol yang belum ada. Eskalasikan kebutuhan koreksi detail lengkap atau penghapusan event kepada pengelola sistem. Jangan membuat ulang event tanpa mempertimbangkan data tamu, QR, foto, billing, dan petugas yang sudah terkait.

### 5.3 Mengelola tamu

Buka **Workspace → Guests** dan pastikan **Current event** benar.

#### Mencari tamu

1. Isi kotak **Search guest**.
2. Pilih **Search**.
3. Untuk menghapus filter, kosongkan kotak lalu pilih **Search** lagi.

Pencarian UI saat ini berdasarkan nama.

#### Menambah tamu

1. Pilih **Add Guest**.
2. Isi **Nama lengkap**.
3. Pada **WhatsApp**, kode `+62` sudah disediakan. Masukkan nomor lokal tanpa `0` di depan, misalnya `812...`.
4. Isi **Email (opsional)**.
5. Pilih **Kategori**: `Family`, `Friend`, `Colleague`, `VIP`, atau `Other`.
6. Isi **Jumlah tamu** antara 1–20.
7. Pilih **Tambah Tamu**.

Guest code dibuat otomatis bila tidak diberikan oleh proses impor/API.

#### Mengedit tamu

1. Pada baris tamu, pilih **Edit**.
2. Ubah data yang diperlukan.
3. Pilih **Simpan Perubahan**.

#### Menghapus tamu

1. Pada baris tamu, pilih **Delete**.
2. Baca konfirmasi dengan teliti.
3. Konfirmasikan hanya bila benar-benar yakin.

Menghapus tamu juga menghapus data invitation, QR, dan attendance terkait. Tindakan ini bukan arsip dan tidak tersedia tombol pemulihan di UI.

#### Import dan export tamu

Guestory backend mendukung impor CSV dan ekspor CSV tamu, tetapi deployed UI `main` saat ini **belum menyediakan kontrol Import/Export pada halaman Guests**. Untuk operasi massal, gunakan proses operasional resmi yang dijalankan pengelola sistem. Format data yang didukung mencakup `guest_code`, `name`, `phone`, `email`, `category`, `group_name`, `guest_count`, `table_number`, dan `notes`; jangan mengunggah file melalui halaman lain.

### 5.4 Undangan personal dan QR

Kontrol undangan dan QR berada pada setiap baris di **Workspace → Guests**.

#### Membuat atau membuka undangan

1. Pilih **Invite** pada tamu.
2. Guestory membuat undangan bila belum ada; bila sudah ada, URL yang sama dipertahankan.
3. Undangan dibuka di tab baru. Jika popup diblokir, salin/buka URL yang ditampilkan pada pesan status.

Tombol **Invite** bukan tombol kirim WhatsApp. Pengiriman pesan dilakukan dari menu **WhatsApp**.

#### Regenerasi undangan

Backend mendukung regenerasi URL undangan, tetapi UI `main` belum menyediakan tombolnya. Karena regenerasi membuat URL lama tidak berlaku, minta pengelola sistem menjalankannya hanya bila tautan bocor atau memang harus diganti.

#### Membuat QR

1. Jika tamu belum memiliki QR, pilih **QR**.
2. Tunggu pesan **QR aktif siap.**
3. Pilih **SVG** untuk mengunduh QR berformat vektor.

#### Regenerasi QR

1. Jika QR sudah ada, tombol berubah menjadi **Regen QR**.
2. Pilih tombol tersebut.
3. Pada konfirmasi **Regenerasi akan mencabut QR lama. Lanjutkan?**, lanjutkan hanya bila QR lama memang harus dibatalkan.
4. Tunggu pesan **QR baru berhasil dibuat.**
5. Unduh SVG baru dan hentikan penggunaan salinan lama.

Hanya satu QR aktif per tamu. Backend juga mendukung revoke/activate QR tanpa regenerasi, tetapi kontrol tersebut belum tersedia di UI `main`.

### 5.5 Invitation Builder

Buka **Workspace → Invitation Builder** dan pastikan **Current event** benar.

#### Memilih tema

Di **01 · Visual direction**, bagian **Choose a mood**, pilih:

- `classic` — editorial dan timeless;
- `garden` — organic dan romantic; atau
- `midnight` — bold dan cinematic.

#### Mengatur bagian undangan

Di **02 · Story flow**, bagian **Arrange sections**:

1. Seret bagian untuk mengubah urutan.
2. Gunakan sakelar untuk menampilkan atau menyembunyikan bagian.
3. Bagian yang tersedia adalah **Opening**, **Event details**, **Photo slideshow**, **Personal QR**, **RSVP**, dan **Photos & album**.

Bagian tersembunyi tetap tersimpan dan dapat diaktifkan kembali.

#### Mengunggah gambar slideshow desain

Di **03 · Slideshow media**:

1. Pilih **Add slideshow images**.
2. Pilih satu atau beberapa JPG, PNG, atau WebP.
3. Batasnya 5 MB per file dan maksimal 12 gambar per event.
4. Tunggu indikator upload selesai.
5. Seret gambar atau gunakan tombol panah atas/bawah untuk mengurutkan.
6. Gunakan tombol berbentuk bintang untuk menjadikannya **Cover · first slide**.
7. Gunakan tombol tempat sampah untuk menghapus gambar setelah membaca konfirmasi.

Gambar ini hanya untuk slideshow desain undangan. Gambar tersebut berbeda dari foto yang diunggah tamu pada album.

#### Mengedit teks dan melihat pratinjau

1. Di **04 · Your words**, ubah field teks yang diperlukan.
2. Gunakan tombol **Mobile**/**Desktop** pada **Live preview** untuk mengganti ukuran pratinjau.
3. Pratinjau memakai nama contoh **Nama Tamu**; undangan asli tetap dipersonalisasi untuk tiap tamu.

#### Menyimpan desain

Pilih **Save & publish design**. Tunggu pesan **Desain tersimpan dan langsung aktif di undangan publik.**

Tombol ini menyimpan desain dan langsung menerapkannya pada undangan yang sudah tersedia. Tombol tersebut **tidak** mem-publish event, membuat undangan tamu, atau mengirim pesan.

### 5.6 Menugaskan Receiver

Buka **Workspace → Receivers**.

#### Menambahkan akun baru

1. Pastikan **Current event** benar.
2. Di **Tambah petugas**, isi **Nama (wajib untuk akun baru)** dan **Email**.
3. Di **Tugaskan ke event milik Anda**, centang satu atau beberapa event.
4. Pilih **Tambah petugas**.
5. Akun baru menerima link aktivasi dan muncul sebagai `PENDING_ACTIVATION` sampai selesai aktivasi.

Jika tidak mencentang event lain, petugas tetap ditugaskan ke Current event.

#### Menugaskan akun yang sudah ada

Masukkan email akun yang sudah terdaftar lalu pilih event. Guestory menggunakan kembali akun itu tanpa mengubah role atau kemampuan utamanya. Email yang sama dapat ditugaskan ke beberapa event.

#### Mengirim ulang aktivasi

Untuk akun yang masih membutuhkan aktivasi, pilih **Resend aktivasi**.

#### Mencabut akses

1. Pastikan event yang dipilih benar.
2. Pada petugas berstatus assignment `ACTIVE`, pilih **Cabut akses**.
3. Akses hanya dicabut dari event tersebut; akun dan penugasan pada event lain tidak dihapus.

Kartu paket menampilkan kapasitas staff per event. Namun, source `main` saat panduan ini ditulis belum menerapkan pemeriksaan `staff_limit` pada proses **Tambah petugas**; tetap gunakan jumlah petugas sesuai paket dan eskalasikan ketidaksesuaian kepada pengelola.

### 5.7 Attendance dan Guest Book

Buka **Workspace → Attendance**.

- Filter **Attendance** menyediakan **All**, **Checked In**, dan **Not Checked In**.
- Panel **Guest status** menampilkan nama, kategori, RSVP, status kehadiran, metode, dan waktu.
- Panel **Guest Book — Checked-in guests** dibentuk otomatis dari check-in QR maupun manual.
- Pilih **Export CSV** untuk mengunduh daftar attendance sesuai filter yang aktif.

Backend juga menyediakan ekspor Guest Book khusus dan filter/sort tambahan, tetapi UI `main` belum menyediakan kontrolnya. File **Export CSV** yang terlihat di layar adalah ekspor attendance.

### 5.8 Moderasi foto tamu

Buka **Workspace → Photos**.

1. Pilih filter **Semua foto**, **Pending**, **Approved**, atau **Rejected**.
2. Pilih **Refresh** bila perlu.
3. Pada kartu foto:
   - **Approve** menampilkan foto di album publik;
   - **Reject** menyembunyikannya dari album publik; dan
   - **Delete** menghapus record dan file foto.

Foto baru dari tamu masuk sebagai `PENDING`. Hanya foto `APPROVED` yang tampil di album undangan. Menghapus foto adalah tindakan permanen dari UI; verifikasi nama tamu dan event lebih dahulu.

### 5.9 WhatsApp dan Settings

#### Mengatur identitas pengirim

1. Buka **Operations → Settings**.
2. Ubah **Nama akun** bila perlu.
3. Pada **Nomor WhatsApp pengirim / akun**, isi nomor lokal setelah `+62` tanpa `0` di depan.
4. Pilih **Simpan Profil**.
5. Periksa nilai **Nomor tersimpan**.

#### Mengirim undangan ke satu tamu

1. Buka **Operations → WhatsApp**.
2. Pastikan Current event dan nama event pada halaman benar.
3. Pada **Guest**, pilih tamu yang memiliki nomor telepon.
4. Pilih **Kirim**.
5. Periksa pesan status dan **Delivery Log**.

Sistem membuat/memastikan undangan personal saat memproses pesan. Status pada log adalah hasil pemrosesan sistem; jangan menganggap pesan sudah diterima hanya karena tombol ditekan.

#### Mengirim massal

1. Pastikan data nomor WhatsApp seluruh tamu sudah benar.
2. Pilih **Bulk**.
3. Guestory memproses seluruh tamu pada event terpilih yang memiliki nomor telepon.
4. Periksa jumlah yang diproses dan **Delivery Log**.

UI saat ini tidak menampilkan layar konfirmasi, pilihan subset, filter log, atau tombol resend. Backend mendukung resend, tetapi belum ada kontrol UI. Karena **Bulk** dapat mengirim ke banyak penerima sekaligus, lakukan pemeriksaan data dan persetujuan komunikasi sebelum menekannya.

### 5.10 Paket, billing, dan kuota

Buka **Operations → Plan & Billing**. Paket berlaku per event dan terpisah dari status `Draft`/`Published`/`Archived`.

| Paket | Harga per event | Tamu | Foto | Retensi | Staff | Branding / fitur |
|---|---:|---:|---:|---:|---:|---|
| Free | Rp0 | 50 | 100 | 7 hari | 1 | Guestory branded, unduh satu foto |
| Basic | Rp99.000 | 300 | 1.000 | 30 hari | 2 | Small branding, ZIP download |
| Premium | Rp249.000 | 1.000 | 5.000 | 90 hari | 5 | Tanpa branding, ZIP download, Google Photos entitlement |

Harga dan kuota di atas mengikuti konfigurasi source `main` pada tanggal panduan ini; selalu utamakan angka yang tampil di layar bila aplikasi diperbarui.

#### Mengaktifkan Free

1. Pilih event.
2. Pada kartu Free, pilih **Aktifkan FREE**.
3. Tunggu status menjadi `FREE · ACTIVE`.

Tanpa billing aktif, sistem tetap menggunakan batas efektif paket Free untuk guest dan photo quota.

#### Memilih paket berbayar

1. Pilih **Pilih Basic** atau **Pilih Premium**.
2. Guestory mengarahkan Anda ke halaman pembayaran aman.
3. Selesaikan pembayaran pada penyedia pembayaran.
4. Setelah kembali ke Guestory, pilih **Sync status**.
5. Pastikan **Current entitlement** menjadi paket yang dipilih dengan status `PAID`.

Jika checkout gagal atau pembayaran masih `PENDING`, jangan mengulang transaksi berkali-kali tanpa memeriksa status/order. **Sync status** hanya aktif jika sudah ada order pembayaran.

#### Saat kuota habis

Guestory menolak penambahan tamu/import yang melewati `guest_limit` dan upload foto yang melewati `photo_limit`. Kartu paket juga menampilkan `staff_limit`, tetapi pemeriksaannya belum diterapkan pada proses penugasan Receiver di source `main`. Hapus data hanya jika memang tidak diperlukan; untuk kebutuhan kapasitas riil, pilih paket yang sesuai dan patuhi kapasitas staff yang ditampilkan.

## 6. Panduan Penerima Tamu atau Receiver

### 6.1 Persiapan awal

1. Selesaikan aktivasi akun dari email.
2. Buka halaman **Receiver** dan pilih **Login Receiver** setelah mengisi email/password.
3. Di dropdown **Event**, pilih event penugasan yang benar.
4. Periksa nama venue dan angka **checked-in dari ... tamu**.
5. Gunakan perangkat dengan koneksi stabil dan browser modern melalui HTTPS.

### 6.2 Memberikan izin kamera

1. Di bagian **Scanner — Scan QR tamu**, pilih **Camera**.
2. Saat browser meminta akses kamera, pilih izinkan.
3. Guestory mencoba memakai kamera belakang (`environment`).
4. Arahkan QR ke area pemindaian, jaga cahaya cukup, dan tunggu hasil validasi.
5. Pilih **Stop** bila ingin mematikan scanner.

Jika izin pernah ditolak, buka pengaturan situs di browser, ubah izin kamera menjadi Allow/Izinkan, lalu muat ulang halaman.

### 6.3 Check-in QR: validate lalu confirm

Pemindaian **tidak langsung mencatat kehadiran**. Ikuti dua tahap berikut:

1. Scan QR dengan kamera, atau tempel token/URL QR pada kotak **Paste token atau URL QR**.
2. Jika ditempel manual, pilih **Validate**.
3. Periksa nama tamu, kategori, dan jumlah orang.
4. Jika hasil mengizinkan check-in, sesuaikan **Hadir aktual** antara 1–20.
5. Pilih **Check-in** untuk menyimpan kehadiran.
6. Pastikan pesan sukses muncul sebelum melayani tamu berikutnya.

Jangan mengonfirmasi bila nama atau event tidak cocok. QR event lain, QR revoked/tidak dikenal, event tidak aktif, dan tamu yang sudah check-in akan ditolak.

### 6.4 Check-in manual

Gunakan ini bila QR rusak, tamu tidak membawa undangan, atau kamera tidak dapat dipakai.

1. Di **Manual — Search guest**, masukkan **Nama, kode, atau nomor HP**.
2. Pilih **Search**.
3. Hasil hanya menampilkan tamu yang belum check-in.
4. Cocokkan nama dan guest code dengan identitas/daftar event.
5. Pilih baris tamu untuk langsung melakukan check-in manual.
6. Pastikan pesan sukses muncul.

Perhatian: pada alur manual UI, memilih hasil langsung menyimpan check-in dengan jumlah tamu yang tercatat; tidak ada langkah konfirmasi atau field **Hadir aktual**. Verifikasi dengan teliti sebelum memilih.

### 6.5 Recent history

Bagian **Recent — Check-ins** menampilkan hingga 10 check-in terbaru pada event terpilih, lengkap dengan waktu, nama, dan metode `QR` atau `MANUAL`. Gunakan untuk memastikan transaksi terakhir tercatat dan untuk menghindari pengulangan.

### 6.6 Berpindah event dan logout

- Ganti dropdown **Event** hanya saat berpindah meja/event yang memang ditugaskan.
- Setelah berpindah event, hasil validasi QR sebelumnya dibersihkan.
- Pilih **Logout** setelah shift selesai atau saat menyerahkan perangkat.

## 7. Perjalanan tamu

1. Tamu menerima tautan undangan personal, biasanya melalui WhatsApp.
2. Saat membuka tautan, status invitation berubah menjadi `OPENED`.
3. Tamu melihat bagian yang diaktifkan Admin Event: detail, slideshow, QR personal, RSVP, dan album.
4. Di bagian RSVP, tamu memilih **Hadir** atau **Tidak hadir**.
5. Jika tersedia, tamu memilih file lalu **Upload foto**. Format: JPG, PNG, atau WebP; maksimal 5 MB.
6. Foto masuk sebagai `PENDING` dan baru terlihat di **Galeri disetujui** setelah Admin memilih **Approve**.
7. Saat tiba, tamu menunjukkan QR personal kepada Receiver.

Tamu harus menjaga tautan dan QR personal karena keduanya terkait identitas undangan. Tamu tidak perlu login.

## 8. Status dan istilah

### Akun

- `PENDING_VERIFICATION` — Event Owner yang mendaftar sendiri belum memverifikasi email.
- `PENDING_ACTIVATION` — akun yang dibuat pengelola belum membuat password melalui link aktivasi.
- `ACTIVE` — akun dapat login.
- `SUSPENDED` — akun ditangguhkan; sesi aktif dicabut.

### Event

- `Draft` — event masih dipersiapkan.
- `Published` — event aktif untuk undangan publik dan validasi QR.
- `Archived` — event dinonaktifkan tanpa menghapus data.

### RSVP, invitation, dan attendance

- `PENDING` — tamu belum memberikan RSVP.
- `ATTENDING` — tamu menyatakan hadir.
- `DECLINED` — tamu menyatakan tidak hadir.
- `DRAFT` — undangan belum dibuat/dikirim.
- `SENT` — undangan personal sudah dibuat untuk dibagikan.
- `OPENED` — tautan undangan pernah dibuka.
- `NOT_CHECKED_IN` — belum tercatat hadir.
- `CHECKED_IN` — sudah tercatat hadir.
- `QR` / `MANUAL` — metode check-in.

### QR dan penugasan Receiver

- `ACTIVE` — QR atau penugasan dapat digunakan.
- `REVOKED` — QR/penugasan dicabut.
- `EXPIRED` — QR sudah kedaluwarsa bila masa berlaku diterapkan.
- `NO_QR` — tamu belum memiliki QR.

### Foto

- `PENDING` — menunggu moderasi.
- `APPROVED` — tampil di album publik.
- `REJECTED` — tidak tampil di album publik.

### WhatsApp

Log dapat menampilkan `PENDING`, `QUEUED`, `SENT`, `FAILED`, atau `SKIPPED`. `SENT` berarti penyedia menerima/memproses pengiriman sesuai integrasi; itu bukan bukti bahwa penerima sudah membaca pesan.

### Billing

- `ACTIVE` — paket Free aktif.
- `PENDING` — pembayaran dibuat dan belum terkonfirmasi.
- `PAID` — pembayaran terkonfirmasi dan entitlement aktif.
- `FAILED`, `EXPIRED`, atau `CANCELLED` — transaksi tidak berhasil/berlaku.

## 9. Pemecahan masalah

### Tidak bisa login

- Pastikan memakai halaman yang sesuai: **Admin** untuk Event Owner/SUPERADMIN, **Receiver** untuk role Receiver.
- Periksa ejaan email dan password.
- Pastikan aktivasi/verifikasi email sudah selesai.
- Jika akun Event Owner ditangguhkan, hubungi SUPERADMIN.
- Gunakan **Lupa password?** bila perlu.

### Link aktivasi/verifikasi/reset tidak berlaku

Link mungkin kedaluwarsa atau sudah pernah digunakan.

- Event Owner hasil provisioning: minta SUPERADMIN memilih **Resend**.
- Receiver: minta Admin Event memilih **Resend aktivasi**.
- Reset password: minta link baru lewat **Lupa password?**.
- Verifikasi pendaftaran mandiri: hubungi pengelola karena tombol resend belum ada di UI.

### Event atau data yang dicari tidak terlihat

- Periksa **Current event**.
- Pilih **Refresh**.
- Pastikan Anda adalah pemilik event atau Receiver dengan assignment `ACTIVE`.
- SUPERADMIN tetap tidak dapat melihat event owner lain.

### Undangan tidak dapat dibuka

Pastikan event sudah `Published`, URL belum diregenerasi, dan undangan berasal dari event yang benar. Event `Draft`/`Archived` tidak disajikan sebagai undangan publik aktif.

### Popup undangan diblokir

Setelah memilih **Invite**, lihat pesan status. Izinkan popup untuk situs Guestory atau buka URL yang ditampilkan secara manual.

### QR gagal divalidasi

- Pastikan Receiver memilih event yang benar.
- Pastikan event `Published`.
- Gunakan QR terbaru jika pernah melakukan **Regen QR**.
- Coba tempel URL/token ke kotak manual dan pilih **Validate**.
- Jangan lanjut jika muncul QR untuk event lain atau duplicate check-in.

### Kamera tidak berfungsi

- Pastikan halaman menggunakan HTTPS.
- Izinkan kamera pada pengaturan situs browser.
- Tutup aplikasi/tab lain yang memakai kamera.
- Pastikan perangkat memiliki kamera.
- Gunakan pencarian manual sebagai fallback.

Pengujian kamera tetap bergantung pada perangkat fisik, browser, pencahayaan, dan fokus kamera.

### Tamu tidak ditemukan pada pencarian manual

- Cari dengan sebagian nama, guest code, atau nomor HP.
- Pastikan event benar.
- Hasil UI hanya menampilkan `NOT_CHECKED_IN`; cek **Recent — Check-ins** atau minta Admin memeriksa Attendance bila tamu mungkin sudah masuk.

### Upload foto gagal

- Gunakan JPG, PNG, atau WebP maksimal 5 MB.
- Periksa koneksi.
- Pastikan kuota foto event belum habis.
- Jangan tutup halaman saat tertulis **Mengupload…**.

### WhatsApp gagal atau tidak terkirim

- Pastikan nomor tamu tersimpan dalam format Indonesia yang benar.
- Periksa **Delivery Log** dan status `FAILED`/`SKIPPED`.
- Pastikan undangan/event benar sebelum mengulang.
- Hubungi pengelola integrasi bila banyak pesan gagal. Jangan melakukan **Bulk** berulang tanpa mengetahui penyebab.

### Paket/pembayaran belum aktif

- Kembali ke **Plan & Billing**.
- Pilih event dan tekan **Sync status** bila ada order.
- Hindari membuat checkout berulang.
- Simpan referensi order yang terlihat dan hubungi pengelola jika status tetap `PENDING` atau `FAILED`.

## 10. Keamanan dan privasi

- Jangan bagikan password, link aktivasi/reset, token, atau screenshot QR di grup publik.
- Perlakukan tautan undangan dan QR sebagai data personal tamu.
- Gunakan akun masing-masing; jangan memakai satu akun Receiver bersama untuk seluruh tim jika dapat dihindari.
- Logout dari perangkat bersama setelah selesai.
- Periksa Current event sebelum mengirim **Bulk**, menghapus data, mengarsipkan event, atau memoderasi foto.
- Minta persetujuan tamu dan patuhi kebijakan komunikasi sebelum mengirim WhatsApp.
- Moderasi foto sebelum **Approve**; jangan publikasikan konten sensitif, memalukan, atau tanpa hak yang memadai.
- Ekspor CSV mengandung data pribadi. Simpan di lokasi terbatas, jangan kirim melalui kanal publik, dan hapus ketika tidak lagi dibutuhkan sesuai kebijakan organisasi.
- Jangan membuka tautan pembayaran dari sumber selain alur **Plan & Billing** Guestory.
- Gunakan **Archive** untuk menonaktifkan event tanpa kehilangan data. Gunakan **Delete** hanya jika konsekuensinya dipahami.

## 11. Checklist operasional event

### Sebelum event

- [ ] Akun Event Owner dan seluruh Receiver sudah `ACTIVE`.
- [ ] Event yang benar dipilih pada **Current event**.
- [ ] Nama, tanggal, venue, dan status event diperiksa.
- [ ] Paket dan kuota tamu/foto/staff mencukupi.
- [ ] Daftar tamu, nomor WhatsApp, kategori, dan jumlah tamu diperiksa.
- [ ] Invitation Builder disimpan melalui **Save & publish design**.
- [ ] Slideshow desain dan teks undangan ditinjau di mode Mobile dan Desktop.
- [ ] Invitation dan QR dibuat untuk tamu yang memerlukan.
- [ ] Satu sampel QR terbaru diuji pada perangkat fisik.
- [ ] Event sudah **Published** sebelum undangan dibagikan.
- [ ] Receiver sudah ditugaskan ke event yang tepat.
- [ ] Perangkat Receiver dapat login, memakai HTTPS, mengakses kamera, dan memiliki koneksi/cadangan daya.
- [ ] Tim memahami urutan **Validate → periksa identitas/jumlah → Check-in**.
- [ ] Prosedur fallback pencarian manual sudah disepakati.
- [ ] Pengiriman WhatsApp diuji secara terbatas sebelum **Bulk**.

### Selama event

- [ ] Receiver memeriksa event aktif sebelum mulai shift.
- [ ] Kamera dan pencarian manual siap digunakan.
- [ ] Nama dan jumlah **Hadir aktual** diperiksa sebelum konfirmasi QR.
- [ ] Manual check-in hanya dipilih setelah identitas cocok.
- [ ] Pesan sukses ditunggu sebelum melayani tamu berikutnya.
- [ ] **Recent — Check-ins** dipantau untuk mendeteksi duplikasi/kesalahan.
- [ ] Admin memantau **Overview** dan **Attendance** lalu melakukan **Refresh** berkala.
- [ ] Foto `PENDING` dimoderasi secara berkala sesuai kebijakan event.
- [ ] Masalah jaringan/kamera dialihkan ke alur manual, bukan dicatat ulang tanpa verifikasi.

### Setelah event

- [ ] Receiver logout dari seluruh perangkat bersama.
- [ ] Admin memeriksa Attendance dan Guest Book.
- [ ] Pilih **Export CSV** untuk arsip attendance jika dibutuhkan.
- [ ] Foto tersisa dimoderasi; konten yang tidak layak ditolak/dihapus.
- [ ] Delivery Log WhatsApp dan status billing diperiksa bila relevan.
- [ ] File ekspor dipindahkan ke penyimpanan aman atau dihapus sesuai retensi.
- [ ] Event diubah menjadi **Archived** setelah akses publik tidak lagi diperlukan.
- [ ] Penugasan Receiver yang tidak lagi diperlukan dicabut per event.

## 12. Batasan antarmuka saat ini

Panduan ini membedakan kemampuan backend dengan tombol yang benar-benar tersedia agar pengguna tidak mencari kontrol yang belum ada.

- UI belum menyediakan Edit/Delete event, walaupun backend mendukungnya.
- UI Guests belum menyediakan Import/Export CSV, walaupun backend mendukungnya.
- UI belum menyediakan regenerasi invitation URL.
- UI hanya menyediakan buat/regenerasi/unduh QR; revoke/activate QR tersedia di backend tetapi belum di UI.
- UI Attendance menyediakan ekspor attendance, bukan tombol ekspor Guest Book khusus.
- UI WhatsApp belum menyediakan filter log, pemilihan subset untuk bulk, atau tombol resend.
- UI pendaftaran mandiri belum menyediakan tombol resend verifikasi email.
- Halaman Receiver hanya menerima role `RECEIVER`; akun SUPERADMIN/EVENT_OWNER yang memiliki assignment receiver belum dapat login ke layar tersebut karena pemeriksaan role frontend.
- UI event creation hanya meminta nama, tanggal, jenis, dan venue; field event lain yang didukung backend belum memiliki editor end-user.
- Paket menampilkan batas staff, tetapi endpoint penugasan Receiver belum menegakkan `staff_limit`.
- Beberapa label masih berbahasa Inggris dan penggunaan istilah tidak sepenuhnya seragam, misalnya **Receivers** vs **Petugas event**, serta **Guests** vs **Tamu event**.

## 13. Dokumentasi terkait

- [Arsitektur akun dan onboarding](account-registration.md)
- [Pengelolaan Receiver multi-event](event-receiver-management.md)
- [Invitation Builder](invitation-builder.md)
- [Album foto tamu yang disetujui](guest-photo-album.md)
- [Catatan perbaikan UI dan QR](REVIEW_FIXES.md)
- [Resolusi pengujian UI dan perangkat](testing-resolution.md)

Dokumen deployment seperti [Production readiness](production-readiness.md) dan [Domain setup](domain-setup.md) ditujukan kepada pengelola teknis, bukan pengguna akhir.
