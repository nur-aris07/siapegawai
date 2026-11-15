# SITE-SETTINGS-01 / Fase C / C-05 — Isolasi uji validasi dari rate limiter

**Status:** REVIEW; belum OWNER-ACCEPTED; belum Git. **Bukan milestone baru.**
**Expected HEAD:** main = `75afa10c94adfc34dfa0134860e781d15a0605c5`.

## Diagnosa berbasis source dan log C-04

- Log owner C-04: dry-run/apply/verify 3/3 PASS; focused PHPUnit **14 PASS, 1 FAIL, 157 assertions**, di `tests/Feature/SiapegawaiSiteSettingsTest.php:232`, `Session is missing expected key [errors]` setelah PUT.
- Di `routes/web.php` terdapat `POST /admin/website/pratinjau` dengan `throttle:20,1` dan `PUT /admin/website` dengan `throttle:10,1`.
- Test C-04 melakukan 10 pasang POST+PUT menggunakan satu user. Laravel 13 `ThrottleRequests` membentuk signature dari user terautentikasi, sehingga counter POST/PUT numerik dapat dipakai bersama. Pada percobaan PUT lanjutan, permintaan dapat ditolak **429 sebelum validasi controller**, bukan menjadi redirect 302 dengan session errors.
- Regex `FORBIDDEN_KEY_PARTS` pada controller C-04 telah memuat 10 contoh yang diuji: SMTP/password/OAuth/token/harga/paket/kuota/tarif/pembayaran/billing/sandi. **Tidak ada bukti yang mendukung penambahan pola regex lagi**; masalah sebelumnya gagal terdiagnosis karena test tidak memeriksa HTTP status.
- Belum ada rekaman status respons PUT gagal pada log owner. Test tambahan di C-05 secara eksplisit memverifikasi pembatas rate sehingga diagnosis dapat dibuktikan secara runtime, bukan diasumsikan.
- Karena rangkaian `&&` berhenti di focused test, **full regression, Vite build, git diff --check belum dieksekusi dalam log C-04**.

## Perubahan C-05 (dibatasi pada tes)

1. **UPDATE** `tests/Feature/SiapegawaiSiteSettingsTest.php`: pada *satu* test matriks validasi nama saja, nonaktifkan middleware `Illuminate\Routing\Middleware\ThrottleRequests` melalui Laravel test harness. Ini **tidak mengubah route/middleware produksi**. Pastikan POST dan PUT me-return redirect sebelum `assertSessionHasErrors('settings')`; apabila respons salah, kegagalan menunjukkan status HTTP nyata.
2. Tambah test independen `test_site_settings_save_throttle_blocks_eleventh_request` **dengan middleware tetap aktif**: 10 PUT invalid (tanpa konfirmasi) memberi session error `confirmed` lalu PUT ke-11 harus 429; tidak ada insert setting maupun audit. Test ini menjaga rate limiting tetap dilindungi.
3. **ADD** dokumen ini. **Tidak ada perubahan** controller, routes, service, JS/CSS/view, package, migration, SQL, Composer, atau `.env`.

## Prosedur owner (CMD root repository)

- Konfirmasi `git rev-parse HEAD`, `git ls-remote --heads origin main`, `git diff --cached --name-only` (harus baseline yang dikunci, staging kosong). Simpan salinan installer C-04 di luar root.
- Letakkan installer C-05 sebagai `siapegawai-update.php`, jalankan `php siapegawai-update.php --dry-run && php siapegawai-update.php --apply && php siapegawai-update.php --verify`.
- Dengan **DB pengujian terisolasi** (jangan jalankan migration reset di DB aktif), jalankan:

```bat
php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests\Feature\SiapegawaiSiteSettingsTest.php && php artisan test && npm run build && git diff --check && git status --short --branch
```

- Target focused **16 tes PASS**, termasuk validasi dua jalur dan HTTP 429. Bila masih gagal, kirim log yang mencantumkan *status respons HTTP* yang diharapkan/diterima; jangan menambah regex secara spekulatif.
- UAT owner: Yajra DataTables, modal Tambah/Edit, nonaktif Hapus, pengaturan tambahan non-public, keamanan nama custom, preview/no write, konfirmasi, desain visual desktop/mobile dan CSP sesuai kontrak.

**Pengujian oleh penyusun:** PHP lint + source/hash audit + installer offline smoke. **Laravel PHPUnit dan Vite di Windows/DB owner belum dijalankan.**

**Gate:** OWNER-ACCEPTED masih OPEN. Git Fase E hanya setelah penerimaan owner. Release readiness tetap OPEN (staging/CSP, independent restore+media privat, legal/operasional).
