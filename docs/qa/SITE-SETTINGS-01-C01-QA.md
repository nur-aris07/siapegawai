# SITE-SETTINGS-01 — Fase C / C-01 — QA & UAT

- **Expected main HEAD**: `75afa10c94adfc34dfa0134860e781d15a0605c5` (NOTIFY-EMAIL-01, OWNER-ACCEPTED).
- **Fase B**: REV-01 disetujui owner dengan penegasan: **seluruh harga, durasi, manfaat Paket, serta hak CAT/QQ/Kelas harus dikelola bersama pada milestone fitur Paket**, bukan Pengaturan Website.
- **Database**: gunakan tabel `site_settings` yang sudah ada (`key`, `value`, `updated_by`, timestamps). Tidak ada migration baru.
- **Fields**: `site_name`, `tagline`, `support_email`, `support_url`, `footer_note`, `social_instagram`, `social_youtube`, `social_tiktok`.
- **Excluded**: flag registrasi, status legal/Terms/Privacy, APP_KEY, SMTP, OAuth, paket, harga, grants, CAT, QQ, Kelas, media upload, script HTML dan URL tidak aman.

## Installer & QA

Installer root `siapegawai-update.php` memakai `--dry-run`, `--apply`, `--verify`, hash SHA-256 kandidat per file, dan memeriksa **setiap UPDATE** terhadap **Git tree baseline HEAD** read-only (`git show HEAD:path`). Diff owner lokal pada file sasaran dianggap konflik. Backup disimpan di `.siapegawai-backup/site-settings-01-c01` (jangan stage ke Git). `--apply` idempotent dan tidak menjalankan SQL, Composer, migrasi, `.env`, ataupun Git write.

Di Windows CMD:

```bat
cd /d E:\NewChapter1\project\siapegawai
"E:\Development\Runtime\PHP\8.3\php.exe" siapegawai-update.php --dry-run && "E:\Development\Runtime\PHP\8.3\php.exe" siapegawai-update.php --apply && "E:\Development\Runtime\PHP\8.3\php.exe" siapegawai-update.php --verify && "E:\Development\Runtime\PHP\8.3\php.exe" artisan optimize:clear && "E:\Development\Runtime\PHP\8.3\php.exe" artisan view:cache && "E:\Development\Runtime\PHP\8.3\php.exe" artisan view:clear && "E:\Development\Runtime\PHP\8.3\php.exe" artisan test tests\Feature\SiapegawaiSiteSettingsTest.php && "E:\Development\Runtime\PHP\8.3\php.exe" artisan test && npm run build && git diff --check && git status --short --branch
```

Test connection must use only `siapegawai_testing`. Never use `migrate:fresh` with the active `siapegawai` database.

## Owner UAT

1. Buka `/admin/website` sebagai superadmin; pastikan operator biasa dan peserta tidak dapat masuk.
2. Edit nama, tagline dan kontak; pratinjau sebelum menyimpan. Pastikan `/`, `/kontak` dan footer publik tetap **belum berubah** ketika preview dibuka.
3. Konfirmasi simpan. Muat ulang `/`, `/kontak`, dan `/paket` untuk memeriksa identitas, footer, link sosial HTTPS dan fallback kosong. Harga/hak paket tidak berubah.
4. Coba URL `javascript:`, `http:`, URL Instagram domain palsu, string HTML, serta field `legal_approved` dan `plan_price` melalui request; server wajib menolak tanpa mutasi.
5. Buka dua tab Pengaturan Website, simpan tab A; tab B yang stale harus ditolak HTTP 409 (bukan menimpa perubahan).
6. Pastikan audit tercatat untuk field yang **benar-benar berubah**; preview dan simpan tanpa perubahan tidak menambah audit.
7. Cek halaman admin dan publik desktop/mobile, keyboard, zoom, footer, serta tidak ada kerusakan shell akun/peserta.

## Belum terverifikasi dari container

- PHPUnit Laravel penuh pada laptop owner dan Vite build owner belum dieksekusi dari installer ini.
- Manual UAT visual pada Windows belum dilakukan. Jangan mencatat OWNER-ACCEPTED atau melakukan commit/push sebelum owner menyatakan acceptance.
- Gate produksi CSP, legal, SMTP reputation, dan verifikasi restore backup tetap OPEN.
