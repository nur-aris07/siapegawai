# PKG-CATALOG-01 — Fase C C-05 / Halaman kelola paket

Status: **QA owner pending**. Fase B approved; source baseline HEAD `4dce1264d613177576867d37eaec0104ba143365`. C-01 hingga C-04 terpasang. C-04 focused 11 PASS/112 assertions dan regression 258 PASS/2444 assertions berdasarkan log owner; C-05 belum diuji oleh owner.

## Masukan UAT owner

Kelola paket terlalu kompleks bila berupa modal. Admin perlu melihat judul, versi, ringkasan, manfaat, draft, dan publikasi secara lengkap di halaman tersendiri. **Tambah paket tetap modal**; **Atur urutan** tetap halaman tersendiri. Tidak ada perubahan database, migrasi, entitlement, pembayaran, atau rule bisnis.

## Perubahan C-05

- Tabel DataTables/SSR memakai link **Kelola** ke `/admin/paket/{plan}` (tautan normal, dapat dipakai tanpa JS), bukan modal.
- GET HTML `/admin/paket/{plan}` sekarang menyajikan identitas, panel **Versi terbit** dengan ringkasan/manfaat, panel **Draft** dengan form judul/ringkasan/manfaat, pratinjau sementara, publish/discard, status penawaran, tautan kebijakan kuota QQ, dan daftar versi historis.
- GET dengan header `Accept: application/json` tetap memberikan bentuk JSON sebelumnya untuk kompatibilitas klien lama.
- Draft dipratinjau tanpa menulis, escaped via DOM `.textContent`; perubahan form yang belum disimpan menonaktifkan tombol publish (progressive enhancement). Server hanya menerbitkan draft tersimpan dengan fingerprint dan konfirmasi yang sudah ada.
- Form normal Laravel CSRF, method PUT, fingerprint, audit, dan role superadmin existing dipertahankan; halaman memanfaatkan responsif dua kolom desktop / satu kolom mobile.
- Pratinjau POST yang sudah ada mengarahkan link kembali ke detail paket, bukan ke seluruh daftar.
- Test fitur khusus menguji halaman HTML, versi published tetap terlihat saat draft berubah, isi manfaat, status draft paket baru, JSON compatibility, no-edit-modal, dan role gate.

## Gate owner

1. Dry-run/apply/verify installer C-05 pada baseline C-04, staging Git kosong, tidak menjalankan SQL.
2. Pada database testing terisolasi: `npm run build && php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests\Feature\SiapegawaiPackageCatalogTest.php && php artisan test && git diff --check && git status --short --branch`.
3. UAT laptop owner: buka Kelola pada Pejuang, Abdi Negara, VIP, dan paket baru; pastikan judul/ringkasan/manfaat published terbaca; buat dan simpan draft, edit, pratinjau tanpa write, batalkan, dan terbitkan dengan konfirmasi; pastikan public tetap published lama sampai publish.
4. Uji 375/393/768/1280 px: halaman tidak terpotong, semua manfaat terbaca, input dan tombol bisa dijangkau, tabel riwayat tetap scroll lokal; tambah paket masih modal; halaman urutan masih bekerja.
5. Review administrator vs participant/guest; anti-stale fingerprint, audit, dan preview escaped. Belum OWNER-ACCEPTED atau Git sampai QA+UAT diterima.

Tidak ada migrasi baru, SQL, `.env`, Composer, atau Git writes oleh installer.
