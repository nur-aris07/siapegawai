# Siapegawai — REL-VERIFY-01B: Cross-role Security & Independent Restore Evidence

**Tanggal:** 12 November 2026 (WIB)  
**Baseline installer:** `9ca59435890a600d4843e857d930709c85479ac2`  
**Scope:** tambah **1 regression feature test, 1 checker file privat read-only, 1 dokumen runbook**. Tidak ada perubahan aplikasi, endpoint, CAT, kuota, Quiz Kilat, migration, seed, atau data produksi.  
**Keputusan rilis:** **NO-GO / NOT YET VERIFIED** selama A-21, A-25, V-09 dan gate manual lain belum memiliki bukti staging.  
**Status awal paket:** *prepared/locally syntax-validated; PHPUnit dan MySQL Windows belum dieksekusi untuk 01B.*

## 1. Hubungan dengan Implementation Plan dan REL-VERIFY-01A

- **A-07, A-09, A-10, A-16, A-19, A-20, A-27**, serta **V-06:** verifikasi status peserta, isolasi role, pembatasan tindakan admin, keamanan PDF privat, pencabutan/aktivasi ulang hak. PHPUnit 01B memperluas regresi antar-domain tetapi **bukan** UAT multi-browser nyata.
- **A-25 (P0):** backup **plus independent restore**, termasuk DB dan private-media disk `class_media_private` yang root-nya `storage/app/siapegawai-class-media`. Checker 01B hanya memberi bukti keberadaan dump/hash dan paritas byte file privat, **bukan** bukti DB berhasil dipulihkan.
- **A-21 / V-09 (P0):** evaluasi CSP report-only pada staging **production-mode** tetap manual. Jangan memaksa `APP_ENV=production` di localhost yang sedang dipakai peserta atau pada sistem aktif.
- **A-23/A-24/V-07 (P1):** 01A sudah lulus 35/35 smoke halaman publik dan nol overflow; UAT shell admin/member, zoom 200%, keyboard/fokus tetap terbuka.
- Kit 01A telah dipublikasikan pada `9ca5943`: read-only audit **32 checks = 7 PASS / 16 OBSERVED / 9 BLOCKED**, dan browser publik 35/35 PASS. Angka 0 FLAG **tidak** mengubah BLOCKED menjadi PASS.

## 2. Tiga artefak REL-VERIFY-01B

| Berkas | Tindakan | Batasan |
|---|---|---|
| `tests/Feature/SiapegawaiReleaseSecurityGateTest.php` | PHPUnit 7 skenario lintas role, perubahan status/role sesi, admin superadmin-only, PDF revoke–regrant | Menggunakan transaksi **hanya** pada DB MySQL `*_testing` yang sudah dimigrasi. Akan menolak environment/db lain. File PDF dibuat pada disk fake. Tidak membuka koneksi produksi dengan sengaja. |
| `tools/rel-verify/SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php` | Periksa keberadaan + SHA-256 dump; bandingkan SHA-256, jumlah dan byte setiap file di dua folder privat | Hanya baca input; menulis **laporan teredaksi** jika `--out-dir` diberikan. Tidak menjalankan restore DB/copy file. Menolak symlink dan direktori source/restore yang saling menumpuk. |
| `docs/qa/REL-VERIFY-01B-SECURITY-AND-RESTORE-RUNBOOK-2026-11-12.md` | Panduan eksekusi dan evidence gate | Tidak mengandung data sensitif atau hasil staging yang belum dibuktikan. |

**Tidak ada installer yang perlu menjalankan migration.** Untuk tes, schema DB `siapegawai_testing` harus sudah tersedia dari tes tahap sebelumnya; jangan arahkan PHPUnit ke DB utama.

## 3. Instalasi Windows (CMD, satu baris per perintah)

Simpan file installer standalone di root, misalnya `siapegawai-update.php`, lalu:

```bat
cd /d E:\NewChapter1\project\siapegawai

git rev-parse HEAD
git status --short --branch
php siapegawai-update.php
php siapegawai-update.php --apply
php siapegawai-update.php --verify

php -l tests\Feature\SiapegawaiReleaseSecurityGateTest.php
php -l tools\rel-verify\SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php
```

Installer akan menerima baseline tepat `9ca59435890a600d4843e857d930709c85479ac2` dan menolak path konflik sebelum mengubah apa pun. Default dry-run, apply 3 ADD, rerun 3 SKIP. Installer tidak mengubah DB, tidak membaca `.env`, dan tidak memerlukan backup source untuk aksi ADD.

## 4. Jalankan PHPUnit security matrix (hanya database `*_testing`)

Dari **CMD**:

```bat
php artisan test --filter=SiapegawaiReleaseSecurityGateTest
php artisan test --filter=SiapegawaiClassMediaTest
php artisan test --filter=SiapegawaiAccountStatusRefreshTest
php artisan test
```

Skenario baru meliputi: guest tidak mendapat route privat; participant tidak masuk route admin dan admin tidak menjadi participant; pending/rejected/suspended diblokir; perubahan role/status dari koneksi DB saat sesi aktif langsung memutus hak; operational admin tidak dapat menetapkan paket atau menangguhkan peserta; PDF privat hanya untuk peserta enrolled aktif, menolak ID kelas lain dan status revoked, kembali dapat diakses setelah regrant tanpa perubahan konten. **Jangan mengeksekusi tes dari database produksi.**

Catat hasil tes, jumlah assertion, dan durasi dari output Windows. **Belum ada angka tes PASS baru** sampai log lokal diterima. Jika gagal, kirim error dan file test mana yang gagal; jangan langsung menganggap cacat aplikasi tanpa membedakan kegagalan fixture/config.

## 5. P0: Rehearsal backup/restore database yang benar-benar terpisah

### Preconditions (operator, bukan tool otomatis)

1. **Staging disposable saja**; akun uji dan file uji, tidak memakai data pengguna asli untuk dibagikan ke chat/Git.
2. Sediakan **dua DB berbeda**: contoh `siapegawai_staging_source` dan DB **baru, kosong, khusus restore** `siapegawai_restore_verify_testing` pada server staging terisolasi. Jangan gunakan DB yang sedang dipakai app.
3. Verifikasi nama DB target dan akun MySQL terpisah. Akun backup hanya baca sumber; akun restore hanya memiliki privilege pada DB target. Simpan credential di MySQL login-path atau secret store **di luar repo**, jangan di argument command, log atau MD.
4. DB target dibuat terlebih dahulu melalui admin DBA; jangan `DROP DATABASE` atau menjalankan script restore apabila target tidak pasti baru/kosong. **Tidak ada contoh perintah DROP** dalam runbook ini.
5. Copy seluruh `storage/app/siapegawai-class-media` ke direktori **fisik terpisah** pada staging restore. Hindari `storage:link`, shared symlink, overwrite asli atau folder sumber yang sama. Sebelum restore ambil sha256 dump dan file privat dalam evidence privat.

### Contoh alur terkontrol (di host staging saja)

**Jangan jalankan contoh ini pada database aktif atau sebelum DBA memverifikasi grant dan DB target yang kosong.** Nama akun `siapegawai_backup` / `siapegawai_restore` adalah nama login-path yang harus dikonfigurasi secara aman terlebih dahulu, bukan password.

```bat
mysqldump --login-path=siapegawai_backup --single-transaction --quick --routines --triggers siapegawai_staging_source --result-file=E:\secure-qa\siapegawai-staging.sql

certutil -hashfile E:\secure-qa\siapegawai-staging.sql SHA256

REM PRECONDITION: target DB siapegawai_restore_verify_testing sudah dibuat, kosong, dan hanya bisa ditulis akun restore
REM PRECONDITION: dump TIDAK menyertakan CREATE DATABASE/USE database sumber (jangan pakai --databases)
mysql --login-path=siapegawai_restore siapegawai_restore_verify_testing < E:\secure-qa\siapegawai-staging.sql
```

**MySQL restore di atas bukan otomatis aman hanya karena namanya berbeda**: periksa isi awal dump sebelum dipakai, pastikan tidak ada `USE`, `CREATE DATABASE`, perintah berbahaya di dump, atau konfigurasi login yang menunjuk server salah. Bila dump mengandung statement lintas database/privilege, hentikan; tinjau DBA. Jika ragu, lakukan impor dengan alat DBA di isolated container/VM dan catat hasilnya.

**Verifikasi database** pada dua koneksi read-only terpisah (source dan restore), bukan satu koneksi yang berubah `USE`:

- `SELECT DATABASE()` harus menunjukkan nama tepat DB sumber/restorasi masing-masing.
- Bandingkan **jumlah tabel**, daftar migration, dan row count tabel penting: `users`, `learning_classes`, `class_enrollments`, `learning_materials`, `learning_material_media`, tabel attempt CAT/QQ serta penggunaan kuota sesuai schema yang benar-benar ada; jangan mengarang nama tabel yang tidak tersedia.
- Periksa foreign key dan sampel aggregate yang tidak memuat data pribadi. Gunakan dataset fixture dummy bila perlu.
- Jalankan aplikasi staging terpisah yang menunjuk ke DB restored dan private-media copy, lalu uji **login akun dummy**, tampilan materi, download PDF privat, penolakan akses peserta lain, revoke–regrant dan skor/riwayat.
- Catat nilai aktual waktu pemulihan (RTO) dan titik pemulihan (RPO). Tidak boleh mengarang PASS karena `mysqldump` exit 0 saja.

### Periksa file privat dan integritas dump menggunakan tool 01B

```bat
php tools\rel-verify\SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php --help

php tools\rel-verify\SGP-REL-VERIFY-01B-PRIVATE-BACKUP-CHECK.php --run --dump=E:\secure-qa\siapegawai-staging.sql --source-private=storage\app\siapegawai-class-media --restored-private=E:\isolated-restore\siapegawai-class-media --out-dir=.siapegawai-backup\rel-verify-01b\parity
```

Opsional tambahkan `--expected-dump-sha256=<sha256_yang_direkam_sebelum_transfer>` (64 digit heksadesimal) untuk mendeteksi perubahan backup saat dipindah. Output:

- `REL-VERIFY-01B-FILE-PARITY.md` dan `.json` di folder diabaikan Git.
- **PASS_FILE_PARITY_ONLY** = salinan seluruh file privat sama secara byte, dump non-kosong dan digest sesuai jika expected hash diberikan. **Ini bukan PASS A-25.**
- **FLAG** = file hilang, berbeda, ekstra, atau digest yang diklaim tidak sesuai; jangan lanjut sign-off.
- **BLOCKED_NO_PRIVATE_FIXTURE** = tidak ada satu pun file untuk diuji; siapkan PDF dummy.
- Tool menolak dua root yang sama/bertumpuk, symlink, file tak terbaca, dan lebih dari 10.000 file. Tidak menyalin file atau menyentuh database.

Jangan commit file `.sql`, backup PDF, screenshot berisi data sensitif, atau hasil privat. **Jangan menempatkan backup di `public/` atau `storage/app/public/`.**

## 6. P0: CSP staging production-mode (A-21 / V-09)

1. Jalankan staging **terisolasi** dengan aset Vite build terbaru dan `APP_ENV=production` sesuai kebijakan staging (bukan production asli).
2. Buka seluruh shell publik, akun, peserta, admin; rekam header `Content-Security-Policy-Report-Only` dari response. Tinjau **Console** browser untuk `violates ... Content Security Policy`; jangan menempelkan cookie/token ke evidence.
3. Periksa asset CSS/JS/fonts, AJAX Quiz Kilat, DataTables/Yajra, toast, foto/ilustrasi, unduhan PDF, dan halaman error. Merekam header saja **tidak** membuktikan nol violation.
4. Jika ada violation valid, catat route, directive, reproduksi (tanpa credential), severity, dan remediasi. Jangan langsung mengubah CSP menjadi enforce sebelum seluruh jalur valid.
5. Gate PASS hanya setelah laporan pelanggaran yang tertangani dan sign-off reviewer. **01B tidak menandai CSP PASS secara otomatis.**

## 7. P1: browser role dan accessibility masih memerlukan pelaksanaan

- Gunakan akun **dummy** guest/pending/participant A/participant B/operational_admin/superadmin; verifikasi direct URL, bukan sekadar menu tersembunyi.
- Periksa 320, 375, 768, 1024, 1440 dan **zoom 200%** pada shell public/account/member/admin; keyboard Tab/Shift+Tab, Enter/Space, fokus terlihat, error labels, radio, modal dan screen reader.
- Kumpulkan screenshot redacted; tandai item A-23/A-24/V-07 dan REL-VIS-01–09 hanya jika bukti lengkap, mengacu register resmi.
- Kelebihan ruang visual pembahasan QQ tetap **P2 accepted**, tidak dibuka ulang sebagai blocker.

## 8. Format bukti dan keputusan

| Gate | Status saat paket diserahkan | Kriteria PASS |
|---|---|---|
| A-07/A-09/A-10/A-20/V-06 security | AUTO-EVIDENCE sebagian, uji 01B belum dijalankan | PHPUnit PASS + review sesi browser dummy berdasarkan rute/role |
| A-19/A-27 private media | AUTO-EVIDENCE sebelumnya | PHPUnit PASS + URL PDF direct, revoked/regrant di browser staging |
| A-25 backup/restore | **OPEN — P0** | Dump terverifikasi, DB restored independen, row counts/foreign keys, private parity, PDF aplikasi nyata, RTO/RPO, reviewer |
| A-21/V-09 CSP | **OPEN — P0** | Report-only production-mode staging, violation reviewed, keputusan enforcement |
| A-23/A-24/V-07 | **OPEN — P1** | Desktop/mobile/zoom/keyboard/fokus dan bukti per shell |
| REL-VIS-01–09 | OPEN | Acceptance spesifik dan sign-off sesuai register resmi |

Minimal metadata laporan: `acceptance_id`, tanggal WIB, commit, environment, skenario, expected, observed, hasil, lokasi bukti *teredaksi*, defect jika ada, reviewer. Jangan mencantumkan PII, password, PDF, raw SQL, session cookie atau `.env`.

## 9. Git setelah verifikasi lokal

Hanya setelah tes feature/full regression dan file checker smoke PASS, stage **tiga file** yang ditambahkan. Jangan `git add .`; `.env.siapegawai.example` tetap untracked. **Tidak boleh mem-commit file laporan backup atau dump.** Jika ada test gagal, perbaiki dan ulangi sebelum commit. Baseline berikutnya ditentukan dari `git rev-parse HEAD` dan `git ls-remote` setelah push; jangan menduga SHA.

**Perbedaan deliverable vs release:** REL-VERIFY-01B **tooling dan automated regression** bisa selesai dan di-commit meski **go-live tetap NO-GO** sampai semua gate P0 benar-benar diuji di staging dan disetujui.
