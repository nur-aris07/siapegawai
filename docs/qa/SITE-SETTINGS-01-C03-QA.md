# SITE-SETTINGS-01 / Fase C / C-03 — DataTables, modal editor, custom settings

**Status:** REVIEW / QA PENDING; belum OWNER-ACCEPTED dan belum Git. **Expected HEAD** `75afa10c94adfc34dfa0134860e781d15a0605c5` pada `main`. Source yang wajib sudah terpasang: C-01 dan C-02 pada hash asli. Installer C-03 fail-closed bila ada konflik.

## Temuan owner dari C-02

- Kolom status `Terisi/Belum diisi` tidak memiliki makna bisnis; hapus, jangan mengganti dengan aktif/nonaktif.
- Pencarian/sort/pagination tabel mengikuti **Yajra DataTables yang sudah ada** (JSON satu URL, JS lokal, tidak memasang jQuery/CDN baru).
- Form Tambah/Edit sebagai native dialog (modal enhanced) dengan fokus dan Escape, bukan panel di bawah tabel.
- **Tambah** harus membuat key baru serta nilai sendiri. C-02 hanya bisa memilih dari 8 key tetap, sehingga tidak sesuai.
- Visual mengikuti `DESIGN-SYSTEM-AND-VISUAL-CONTRACT.md`, admin data-first, Plus Jakarta Sans, navy/gold terbatas, border halus dan radius kecil.

## Scope keamanan

- Delapan key public bawaan tetap dikelola melalui `PublicSiteSettings::FIELDS` dengan validasi URL/email dan render aman yang sama. `PublicSiteSettings` tidak diubah.
- Setting baru dibuat berdasarkan **Nama pengaturan + Nilai**, sistem mengubah nama menjadi key `custom_...`. Key immutable; nilai dapat diedit. Penghapusan tetap disabled dan tidak ada DELETE route.
- Namespace custom hanya untuk metadata/teks biasa: panjang <=500, menolak HTML/control chars; nama menyerupai secret/legal/SMTP/OAuth/hak akses/paket/harga ditolak. Maksimum 100 custom setting, tanpa migration.
- Custom setting **inert**: disimpan dan diaudit di `site_settings` yang sudah ada, tetapi tidak masuk `PublicSiteSettings::all()`, public pages, feature flags, atau entitlements sampai ada integrasi eksplisit terpisah. Jangan menyimpan token/password sebagai custom setting.
- Preview tidak menulis DB; simpan memerlukan konfirmasi + snapshot-version recheck dalam lock transaction, audit hanya saat perubahan. CSP: JS eksternal Vite, DOM dengan `textContent`, Blade escaped.
- Tidak mengubah routes, models, table schema, `.env`, composer, paket, CAT, QQ, kelas, SMTP, Google, atau legal.

## Manifest C-03

- UPDATE `app/Http/Controllers/Admin/SiteSettingsController.php`
- UPDATE `resources/views/admin/site-settings/index.blade.php`
- UPDATE `resources/views/admin/site-settings/preview.blade.php`
- UPDATE `resources/css/admin-v1.css`
- UPDATE `resources/views/layouts/admin.blade.php`
- UPDATE `vite.config.js`
- UPDATE `tests/Feature/SiapegawaiSiteSettingsTest.php`
- ADD `resources/js/admin-site-settings.js`
- ADD `docs/qa/SITE-SETTINGS-01-C03-QA.md`

## QA yang harus dijalankan di mesin owner (bukan bukti pengujian oleh penyusun)

1. `php siapegawai-update.php --dry-run`, lalu `--apply`, lalu `--verify` setelah memeriksa HEAD dan remote dan staging kosong. Installer yang sama harus no-op bila di-apply ulang.
2. `php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests\\Feature\\SiapegawaiSiteSettingsTest.php && php artisan test && npm run build && git diff --check && git status --short --branch`
3. Pastikan test menggunakan DB testing terisolasi, **jangan jalankan `migrate:fresh` di DB aktif**.
4. UAT browser desktop 1366/1440 dan mobile 375px (cek juga 320/768/1024): tabel cari nama/kunci/nilai, urut 3 kolom, 10/25/50 per halaman, paginasi, empty/error states. Bandingkan dengan panel peserta/soal.
5. Modal tambah: isi **Nama** dan **Nilai** baru, lihat pratinjau yang belum menyimpan; konfirmasi simpan, key `custom_*` baru muncul di tabel. Coba duplikasi, HTML, terlalu panjang, nama sensitif; harus ditolak. Halaman publik tidak otomatis menampilkan custom setting.
6. Modal edit key bawaan dan custom: fokus keyboard, Escape, Tutup/Batal, validasi inline, stale tab 409, NOOP tidak membuat audit, Hapus disabled dan DELETE 405.
7. Public name/tagline/kontak/footer bawaan tetap bisa disimpan dan terlihat di website; URL sosial HTTPS/domain tetap divalidasi; preview tidak mengubah publik. Non-superadmin tidak bisa akses.
8. Periksa CSP/report console, fokus dan urutan tab, zoom/overflow dan data berbahaya ter-escape. Screenshot sebelum/sesudah + timestamp QA sebagai bukti acceptance.

**Release readiness OPEN** sampai staging CSP, restore independen+private media, legal dan operasi nyata ditandatangani terpisah.
