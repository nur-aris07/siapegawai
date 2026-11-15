# PKG-CATALOG-01 / Fase C / C-02 — QA koreksi fixture dan Vite

Status: **QA OWNER PENDING**; bukan OWNER-ACCEPTED; tidak ada perubahan produksi.
Baseline Git: `4dce1264d613177576867d37eaec0104ba143365`. C-01 sebelumnya telah di-apply dan di-verify.

## Bukti kegagalan C-01 dari laptop owner

`SiapegawaiPackageCatalogTest`: **6 failed, 2 passed (42 assertions)**. Lima pengujian gagal karena Laravel Vite tidak menemukan `resources/css/pkg-catalog.css` di manifest build lama (HTTP 500, bukan kegagalan bisnis). Satu pengujian kuota gagal karena fixture memanggil `MemberPlanService::setPlan()` terhadap paket yang masih draft, padahal service dengan benar menolak paket yang belum dipublish atau telah diarsipkan.

## Koreksi terbatas

- **Hanya pengujian:** `SiapegawaiPackageCatalogTest::setUp()` memakai `withoutVite()` untuk memisahkan verifikasi HTML/fitur dari status manifest build aset. Middleware throttle masih diuji oleh `SiapegawaiPackageThrottleTest`, tidak dinonaktifkan pada produksi.
- **Hanya fixture:** tes kuota menerbitkan draft paket dahulu melalui `PackageCatalogService::publish()` sebelum `MemberPlanService::setPlan()`. Tidak melonggarkan guard, hak akses, atau aturan paket.
- **Tidak ada:** perubahan app, JS/CSS, migration, SQL, service, route, grant, komersial, atau Git.

## Urutan owner pada database testing terisolasi

1. Pastikan backup MariaDB yang dapat dibaca telah diambil sebelum migration sebelumnya; C-02 tidak meminta migration ulang. Periksa `php artisan migrate:status` jika ingin mengkonfirmasi migration C-01.
2. Verifikasi installer: `php siapegawai-update-PKG-CATALOG-01-C02-REVIEW.php --dry-run`, lalu `--apply`, lalu `--verify`.
3. **Build sebelum PHPUnit**: `npm run build` agar `public/build/manifest.json` diperbarui untuk UI aktual, termasuk `resources/css/pkg-catalog.css` dan `resources/js/admin-package-catalog.js`.
4. `php artisan optimize:clear`; `php artisan test tests\Feature\SiapegawaiPackageCatalogTest.php`; `php artisan test tests\Feature\SiapegawaiPackageThrottleTest.php`; `php artisan test`; `git diff --check`; `git status --short --branch`.
5. UAT publik, peserta, admin, modal, draft/publish, permintaan WhatsApp, serta verifikasi manual paket baru.

Jangan menjalankan `migrate:fresh` pada database owner. Jika QA gagal, catat status HTTP dan exception sebenarnya serta versi build sebelum revisi berikutnya.
