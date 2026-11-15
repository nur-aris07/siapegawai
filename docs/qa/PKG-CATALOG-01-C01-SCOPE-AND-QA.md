# PKG-CATALOG-01 — C-01 (satu implementasi lengkap, review owner)

Status: FASE C / C-01 — BUKAN OWNER-ACCEPTED. Baseline HEAD `4dce1264d613177576867d37eaec0104ba143365`.

## Scope terpasang dalam satu installer

- Satu identitas `member_plans` yang dinamis; tiga paket awal dan ID/grant/kuota lama tetap utuh.
- Migration baru `2026_11_15_000020...` menambah status paket, pointer versi published, snapshot `member_plan_versions`, serta `package_upgrade_requests`.
- Versioning: satu draft yang dapat diedit/dibatalkan, published lama tetap tampil, publish atomic, audit dan deteksi stale fingerprint.
- Admin DataTables (Yajra endpoint + JS lokal), modal Tambah/Edit tanpa reload, preview, terbit, batal, ketersediaan/arsip, antrean upgrade.
- Katalog publik dan peserta dinamis, paket peserta via `MemberPlanService`; pengajuan idempotent, reference non-PII, WhatsApp resmi (jika terkonfigurasi), penyelesaian manual oleh superadmin.
- Tidak ada payment gateway, transaksi pembayaran, upload bukti, auto entitlement CAT/Kelas, atau klaim kuota/harga tidak terverifikasi.
- Kuota QQ tetap diambil dari `qq_quota_policies` per plan (tidak dikonfigurasi => deny). `TryoutPackage` CAT tidak diubah.

## Checklist operator sebelum apply / migrate

1. `git rev-parse HEAD` dan `git ls-remote --heads origin main` harus sama dengan full SHA baseline di atas. Pastikan staging kosong, tidak ada perubahan lokal pada file manifest yang akan di-UPDATE. Jangan commit/push otomatis.
2. Simpan copy installer yang sedang aktif di luar root repo. `php siapegawai-update-PKG-CATALOG-01-C01-REVIEW.php --dry-run`, lalu `--apply`, lalu `--verify`. Installer tidak mengakses DB, menjalankan migration, mengubah `.env`/Composer, atau menjalankan Git write.
3. **Sebelum menjalankan migration**, backup MariaDB development menggunakan prosedur backup database operator yang sudah terbukti; catat lokasi, SHA-256, ukuran, dan keberhasilan pemeriksaan backup. Jangan mengetik kata sandi DB di percakapan/log. Pastikan target bukan production dan mekanisme restore independen sudah ditinjau. Jangan `migrate:fresh` / `db:wipe` pada DB pemilik.
4. Tinjau source migration dan rencana SQL pada database **development salinan** (perintah `migrate --pretend` dapat tidak merepresentasikan langkah backfill yang membaca data existing); kemudian `php artisan migrate` hanya setelah backup terverifikasi dan persetujuan operator. **Migration bukan bagian dari installer source.** Rollback otomatis sengaja fail-closed bila paket/versi baru atau request sudah ada; gunakan restore DB yang telah diuji.
5. `php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests/Feature/SiapegawaiPackageCatalogTest.php && php artisan test && npm run build && git diff --check && git status --short --branch` pada environment/test DB terisolasi. Bandingkan regresi terhadap baseline C-06: 246 tests / 2317 assertions (bukan target angka yang otomatis sama).
6. UAT sebagai guest, peserta aktif, admin operasional dan superadmin: 0/1/N paket, copy seeder tiga paket, plan keempat, edit draft lalu publik masih vN, preview, publish vN+1, discard, archive, modal, pagination/search, server-rendered table fallback (aksi admin menggunakan JS), keyboard focus, mobile 320/375/768/1024, desktop 1366/1440, CSP report console.
7. UAT request: guest tertolak; peserta tidak dapat meminta paket sendiri/closed; dua submit satu ref; nomor WA kosong tidak mengeluarkan link; nomor valid tidak memasukkan email/nominal/PII; admin memverifikasi manual dan mengeksekusi sekali; peserta lain tidak dapat membaca ref; perubahan paket tidak reset QQ usage/CAT/Kelas.
8. **Sebelum Fase E**: owner acceptance atas scope lengkap, QA dan UAT; `git add --` path manifest eksplisit, `git commit -m ... -m ...`, `git push`, SHA lokal = remote. Release readiness tetap OPEN sampai staging/recovery/legal selesai.

## Batas bukti

Penyusun installer dapat melakukan PHP lint, JS syntax, manifest hash-conflict smoke dan pemeriksaan statis source pada lingkungan terpisah. Tidak dapat mengklaim telah menjalankan PHPUnit Laravel, MariaDB Windows, Vite lengkap, atau UAT pada laptop owner. Setiap FAIL baru ditangani sebagai revisi C-02 hanya berdasarkan bukti.
