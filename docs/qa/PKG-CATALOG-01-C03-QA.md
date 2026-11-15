# PKG-CATALOG-01 / Fase C / C-03 — Perbaikan UAT mobile + urutan paket

**Status:** QA/UAT owner pending. Baseline HEAD `4dce1264d613177576867d37eaec0104ba143365` dengan C-01 dan C-02 terpasang, Git staging kosong.

## Temuan dan perubahan

1. **Mobile overflow:** tabel 660px tidak boleh memperlebar seluruh halaman; pembungkus tabel menahan scroll horizontal. Dialog modal dipindah ke `body` sebelum native `showModal()`, berukuran sesuai `100dvh` dan viewport, dapat scroll vertikal, heading tetap terlihat, serta Tutup/Escape mengembalikan fokus ke tombol pemicu. Cek 375, 393, 768, 1280px.
2. **Label penawaran:** `requestable`, `planned`, `closed`, `archived` diganti label Indonesia di Blade awal **dan** JSON/DataTables JS, tanpa memodifikasi nilai enum database.
3. **Pengaturan urutan terpisah:** menu **Atur urutan** menuju `/admin/paket/urutan`. Form tambah tidak lagi meminta urutan; paket baru otomatis diletakkan di akhir. Halaman urutan memuat daftar **lengkap** (tidak subset pencarian/paginasi), drag-and-drop melalui pegangan di desktop, serta tombol Naik/Turun untuk touch, keyboard, dan aksesibilitas. Pindah baris tidak menulis DB sampai tombol Simpan.
4. **Save aman:** hanya superadmin aktif, konfirmasi eksplisit, validasi exact-set ID tanpa duplikat/foreign/omission, fingerprint stale, transaksi, audit satu kali jika berubah. Perubahan `tier` hanya berpengaruh pada urutan visual, bukan `MemberPlanService` atau kebijakan kuota/hak akses.
5. **Batas existing:** `tier` adalah `unsignedTinyInteger` (maksimal 255), jadi implementasi tetap fail-closed saat kapasitas terlampaui; tidak menaikkan kapasitas kolom tanpa migrasi/database backup terpisah. Tidak ada migration baru pada C-03.

## Owner QA (DB test terisolasi)

1. Jalankan installer `--dry-run`, `--apply`, `--verify`; jangan jalankan migration ulang.
2. `npm run build && php artisan optimize:clear && php artisan view:cache && php artisan view:clear`.
3. `php artisan test tests\Feature\SiapegawaiPackageCatalogTest.php && php artisan test tests\Feature\SiapegawaiPackageThrottleTest.php && php artisan test && git diff --check && git status --short --branch`.
4. UAT desktop: Atur urutan → drag pegangan → batal (tidak berubah) → ulangi → simpan (berubah sesuai urutan). Pastikan draft/versioning dan antrean upgrade tetap normal.
5. UAT mobile: 375/393px, layar tidak bisa digeser horizontal secara keseluruhan; tabel bisa di-scroll **di dalam panel**. Modal Tambah dan Kelola utuh, seluruh aksi bisa dicapai via scroll vertikal, Tutup/Escape; halaman pengurutan Naik/Turun dengan target sentuh >=44px, bisa di-scroll, konfirmasi, Simpan.
6. UAT concurrent: buka dua tab Atur urutan, simpan tab pertama, tab kedua harus menolak urutan usang. Simpan tanpa perubahan tidak mencatat audit baru.

Tidak ada SQL/.env/Composer/Git oleh installer. `PKG-CATALOG-01` belum OWNER-ACCEPTED sampai owner memeriksa pengalaman mobile dan QA.
