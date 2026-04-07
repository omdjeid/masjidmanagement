# Contributing Guide

Terima kasih sudah membantu mengembangkan template ini.

## Branching

- `main` untuk kode stabil
- `develop` untuk integrasi perubahan aktif
- `feature/<nama-fitur>` untuk fitur baru
- `fix/<nama-perbaikan>` untuk bug fix umum
- `hotfix/<nama-hotfix>` untuk perbaikan mendesak dari `main`

## Workflow Singkat

1. Buat branch dari `develop`.
2. Kerjakan perubahan dengan commit yang jelas.
3. Pastikan perubahan tidak membawa file lokal seperti `.env` atau upload.
4. Jalankan pengecekan sintaks PHP seperlunya, misalnya `php -l nama_file.php`.
5. Ajukan merge kembali ke `develop`.

## Commit Style

Gunakan pesan commit yang singkat, jelas, dan berorientasi hasil. Contoh:

- `Initialize public template repository`
- `Add first-admin setup flow`
- `Refine public settings defaults`

## Hal yang Perlu Dijaga

- jangan commit kredensial
- jangan commit isi folder upload lokal
- usahakan default branding tetap generik agar repo tetap reusable
- jaga kompatibilitas dengan shared hosting PHP standar
