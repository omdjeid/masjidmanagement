# Mosque Website Template

Template website masjid berbasis PHP dan MySQL yang dirancang agar mudah dipakai ulang oleh banyak pengelola masjid. Fokus utamanya adalah instalasi sederhana, panel admin yang langsung siap dipakai, dan alur setup yang tidak mengharuskan pengguna mengubah source code inti.

## Highlights

- panel admin untuk mengelola jadwal kajian, artikel, video, infaq, laporan, kategori, dan user
- konfigurasi lokal memakai `.env`
- pembuatan super admin pertama langsung dari browser
- routing publik ramah SEO dengan `.htaccess`
- cocok untuk XAMPP, shared hosting PHP, atau server LAMP standar

## Stack

- PHP
- MySQL / MariaDB
- HTML, CSS, JavaScript vanilla
- Apache rewrite rules via `.htaccess`

## Fitur Utama

- Homepage dengan widget jadwal shalat, ticker, artikel unggulan, video unggulan, dan campaign infaq
- Halaman publik untuk jadwal, artikel, video, laporan, lokasi, dan infaq
- Dashboard admin untuk pengelolaan konten
- Login admin dengan CSRF protection dan throttle percobaan login
- Master kategori dan pengaturan SEO dasar
- Upload gambar artikel dan galeri lokal

## Quick Start

1. Clone repository ini.
2. Salin `.env.example` menjadi `.env`.
3. Sesuaikan `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.
4. Buat database baru di MySQL atau MariaDB.
5. Import `sql/schema.sql` ke database tersebut.
6. Buka `APP_URL/dashboard/setup-admin`.
7. Buat akun super admin pertama.
8. Login ke `APP_URL/dashboard/login`.
9. Lengkapi pengaturan masjid melalui menu `Settings`.

## Konfigurasi Environment

Contoh isi `.env`:

```env
APP_URL=http://localhost/masjid-template

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=masjid_template
DB_USER=root
DB_PASS=
```

## Struktur Penting

- `config/database.php`: konfigurasi koneksi database
- `includes/env.php`: loader `.env`
- `includes/auth.php`: auth, session, role, CSRF, dan bootstrap admin
- `includes/configuration.php`: default setting dan pembacaan konfigurasi situs
- `setup-admin.php`: pembuatan akun super admin pertama
- `sql/schema.sql`: struktur tabel dan default data template

## Cara Pakai Sebagai Template

Repository ini memang disiapkan untuk dipakai ulang. Alur yang disarankan untuk pengguna baru:

1. clone repository
2. buat file `.env`
3. buat database kosong
4. import `sql/schema.sql`
5. buka halaman setup admin
6. isi data masjid sendiri dari dashboard

Dengan alur ini, pengguna cukup mengganti database dan konfigurasi lokal tanpa perlu membongkar file PHP inti.

## Deployment Notes

- pastikan Apache mengaktifkan `mod_rewrite`
- arahkan document root ke folder project ini
- jangan commit file `.env`
- folder `assets/uploads/` memang diabaikan git agar media tiap instalasi tetap terpisah
- untuk hosting production, isi `APP_URL` dengan domain final

## Branch Strategy

Repository ini memakai struktur branch sederhana:

- `main`: branch stabil untuk versi yang siap dipakai atau dipublikasikan
- `develop`: branch integrasi untuk pekerjaan aktif sebelum naik ke `main`
- `feature/<nama-fitur>`: untuk pengembangan fitur baru
- `fix/<nama-perbaikan>`: untuk bug fix biasa
- `hotfix/<nama-hotfix>`: untuk perbaikan cepat yang perlu segera dibawa ke `main`

Alur kerja yang disarankan:

1. buat branch baru dari `develop`
2. kerjakan perubahan di branch tersebut
3. merge kembali ke `develop`
4. merge `develop` ke `main` saat siap rilis

## Contributing

Panduan kontribusi singkat tersedia di `CONTRIBUTING.md`.

## Repository Hygiene

- `.env` tidak ikut ke repository
- upload lokal tidak ikut ke repository
- default branding sudah dibuat generik agar aman dijadikan template publik

## Next Ideas

- tambah installer web untuk setup database otomatis
- tambah export / import konten
- tambah test dasar untuk flow login dan konfigurasi
- tambah GitHub Actions untuk lint PHP
