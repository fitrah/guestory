# Guestory

**Every Guest Has a Story**

Guestory adalah platform pengelolaan event yang menghubungkan undangan digital, RSVP, QR check-in, buku tamu, petugas event, dan album foto.

## Fitur utama

- Manajemen event dan tamu
- Invitation Builder dengan pilihan tema dan susunan section
- Slideshow aset desain yang diunggah langsung oleh pemilik event
- Undangan personal dan RSVP tanpa akun tamu
- QR unik per tamu untuk check-in
- Pengelolaan petugas untuk beberapa event
- Moderasi dan album foto tamu
- Pengiriman undangan WhatsApp
- Paket dan kuota per event

## Teknologi

- **API:** Laravel dan PostgreSQL
- **Frontend:** React, TypeScript, dan Vite
- **Runtime:** PHP-FPM dan PM2

## Struktur proyek

- `apps/api` — API Laravel, model, migration, dan test
- `apps/web` — frontend React/Vite
- `docs` — dokumentasi fitur dan deployment
- `deploy` — contoh konfigurasi deployment
- `ecosystem.config.cjs` — definisi proses PM2

## Menjalankan secara lokal

1. Siapkan konfigurasi lokal dari file contoh:

   ```sh
   cp apps/api/.env.example apps/api/.env
   ```

2. Isi konfigurasi database dan layanan lain melalui environment lokal. Jangan commit `.env` atau kredensial.

3. Pasang dependency dan jalankan migration:

   ```sh
   cd apps/api
   composer install
   php artisan key:generate
   php artisan migrate --seed

   cd ../web
   npm ci
   ```

4. Dari root proyek, jalankan API dan frontend:

   ```sh
   npm run api
   npm run dev:web
   ```

## Pemeriksaan kualitas

```sh
npm run test:api
npm run lint:web
npm run build:web
```

## Dokumentasi

- [Panduan pengguna lengkap (SUPERADMIN, Admin Event, dan Receiver)](docs/user-guide.md)
- [Invitation Builder](docs/invitation-builder.md)
- [Account registration](docs/account-registration.md)
- [Event receiver management](docs/event-receiver-management.md)
- [Approved guest photo album](docs/guest-photo-album.md)
- [Admin UI revamp](docs/admin-ui-revamp.md)
- [Testing resolution](docs/testing-resolution.md)
- [Production readiness](docs/production-readiness.md)
- [Domain setup](docs/domain-setup.md)

## Keamanan konfigurasi

Repository tidak menyimpan kredensial produksi. Gunakan environment variables atau secret manager pada masing-masing environment dan jangan memasukkan password, token, private key, atau file `.env` ke Git.
