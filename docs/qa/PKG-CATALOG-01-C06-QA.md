# PKG-CATALOG-01 / Fase C / C-06 — Isolasi pengujian HTML dan JSON

Status: QA owner pending. Baseline Git sebelum milestone: `4dce1264d613177576867d37eaec0104ba143365`; C-01 sampai C-05 sudah terpasang.

## Temuan C-05

Owner melaporkan focused test 12 PASS / 1 FAIL (138 assertions) pada `test_manage_package_uses_full_page_and_shows_published_snapshot_and_draft_separately`. Assertion `assertSee('Versi terbit')` menerima JSON dengan `code`, `published`, dan `draft`, bukan HTML.

Penyebab yang diidentifikasi pada source C-05: satu metode test memanggil `withHeader('Accept', 'application/json')` untuk menguji kontrak JSON dan kemudian memanggil `get()` lagi tanpa mereset header pada test client yang sama. Header default tersebut persisten, sehingga controller `show()` secara sah memilih jalur `wantsJson()` untuk permintaan berikutnya. Screenshot owner membuktikan halaman Kelola sudah tampil normal pada navigasi browser HTML.

## Perubahan C-06

- Memindahkan assertion klien JSON ke **test method tersendiri**, sehingga `Accept: application/json` tidak memengaruhi pemeriksaan HTML berikutnya.
- Test halaman HTML tetap memeriksa versi terbit, manfaat, draft v2, preview, publish, discard, dan bahwa halaman publik tetap menampilkan versi lama sampai publish.
- Test klien JSON memastikan `code`, `published.title`, dan `published.summary` tetap dikembalikan.
- Tidak ada perubahan controller, routes, services, Blade, CSS/JS, database, migration, legal, paket maupun entitlement.

## QA gate

1. Periksa SHA-256 file installer dan lakukan `--dry-run`, `--apply`, `--verify` pada root Laravel dengan staging Git kosong.
2. Pada database testing terisolasi: `php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests\Feature\SiapegawaiPackageCatalogTest.php && php artisan test && git diff --check && git status --short --branch`.
3. Target focused: **14 tes PASS**, termasuk test JSON dan HTML terpisah. Full regression dan UAT owner tetap menjadi gate penerimaan.
4. Tidak perlu mengulang migration atau Vite build untuk patch tes ini; tidak ada perubahan aset.

Installer tidak menjalankan SQL, migration, Composer, `.env`, atau Git writes. Release readiness tetap OPEN.
