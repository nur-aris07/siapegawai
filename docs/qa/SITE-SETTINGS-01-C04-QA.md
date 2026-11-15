# SITE-SETTINGS-01 / Fase C / C-04 — Koreksi validasi nama setting tambahan

**Status:** REVIEW / belum OWNER-ACCEPTED / belum Git. **Bukan milestone baru.**
**Expected HEAD:** `main` = `75afa10c94adfc34dfa0134860e781d15a0605c5`.

## Temuan aktual dari log owner C-03 (15 November 2026)

- `--dry-run`, `--apply`, `--verify`: PASS; 9 C-03 file + 7 C-02 + 10 C-01 prerequisites.
- Focused test `SiapegawaiSiteSettingsTest`: **14 PASS, 1 FAIL, 139 assertions**; gagal di `tests/Feature/SiapegawaiSiteSettingsTest.php:229` (`Session is missing expected key [errors]`).
- Rangkaian `&&` berhenti pada focused test; **full regression, Vite, git diff --check tidak berjalan pada run tersebut**.
- Penyebab dari source C-03: `Harga Paket` menjadi `custom_harga_paket`; regex larangan nama memblokir `price`/`quota`/`payment`, tetapi tidak memblokir padanan bahasa Indonesia `harga`/`paket`.

## Perubahan C-04 (minimal)

- UPDATE `app/Http/Controllers/Admin/SiteSettingsController.php`: perluas larangan nama kunci tambahan untuk konsep paket, harga, kuota, pembayaran, dan kredensial yang padanannya sebelumnya belum tercakup (mis. `harga`, `paket`, `biaya`, `tarif`, `kuota`, `langganan`, `pembayaran`, `bayar`, `billing`, `package`, `plan`, `subscription`, `checkout`, `sandi`, `rahasia`, `kredensial`). Validasi berlaku pada preview dan save, termasuk pengubahan custom existing.
- UPDATE `tests/Feature/SiapegawaiSiteSettingsTest.php`: uji nama sensitif Indonesia/Inggris pada **POST preview dan PUT save langsung**, serta pembuktian tidak ada setting/audit baru. Nama aman `Judul informasi beranda` tetap boleh.
- ADD dokumen ini sebagai catatan temuan dan QA.
- **Tidak** mengubah service, UI DataTables/modal, routes, JS/CSS, migration, tabel, `.env`, Composer, modul paket atau konfigurasi rilis.

> Pengaman tekstual bersifat pembatas *nama setting*, bukan klasifikasi semantik sempurna. Custom setting tetap inert: tidak dihubungkan ke secret, paket, entitlement, legal maupun public rendering.

## Prosedur owner (Windows CMD, root repo)

1. Pastikan `git rev-parse HEAD` serta `git ls-remote --heads origin main` sama dengan SHA di atas; `git diff --cached --name-only` kosong. Cadangkan installer C-03 ke luar root proyek.
2. Ganti root `siapegawai-update.php` dengan installer C-04, kemudian `php siapegawai-update.php --dry-run` dan periksa **2 UPDATE + 1 ADD**. Terapkan dengan `--apply`, lalu `--verify`. Installer menolak konflik hash dan melakukan backup sebelum UPDATE ke `.siapegawai-backup/site-settings-01-c04/`.
3. Jalankan dengan database **testing terisolasi** (jangan menjalankan `migrate:fresh` pada DB aktif):

```
php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests\Feature\SiapegawaiSiteSettingsTest.php && php artisan test && npm run build && git diff --check && git status --short --branch
```

4. UAT: POST preview dan PUT save `Harga Paket`, `Paket VIP`, `SMTP Password`, `OAuth Token` harus ditolak, tidak menambah row/audit; nama non-sensitif dapat ditambah dan diedit; modal, DataTables, tombol Hapus disabled, edit builtin, escaping, stale version 409 tetap berfungsi.
5. UAT visual desktop/mobile dan CSP browser masih wajib bila belum diterima owner; hasil test otomatis tidak menggantikan owner acceptance.

**Release readiness tetap OPEN** untuk staging CSP, independent recovery + media privat, legal, operasi eksternal, dan sign-off.
