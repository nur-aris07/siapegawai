# Siapegawai — REL-VERIFY-01C v1.15: Independent MySQL Restore & CSP Staging Evidence

**Tanggal:** 12 November 2026 (WIB)  
**Baseline instalasi:** `cac9fa670e100155d6297ab654e7c1e971afed84` (`REL-VERIFY-01B` sudah Git Published)  
**Scope kode:** **2 tool baru + 1 dokumen** (add-only). Tidak mengubah route, controller, middleware, policy, database, migration, `.env`, CAT, Latihan Kelas, Quiz Kilat, atau kuota.  
**Release:** **NO-GO sampai bukti nyata A-25 (restore) dan A-21/V-09 (CSP staging), plus gate lain, ditinjau reviewer.**

## 1. Hubungan langsung ke plan dan tahap sebelumnya

| Gate | Kondisi sebelum 01C | Bukti yang dicari dalam 01C | Yang tidak boleh dianggap lulus otomatis |
|---|---|---|---|
| A-25 P0 | 01B: penguji paritas file privat/dump pada fixture PASS; restore MySQL nyata OPEN | Dump dari sumber staging, restore ke DB target independen, struktur + exact row counts + migrasi + FK/index cocok, paritas PDF privat, smoke aplikasi, RTO/RPO | Dump ada, dummy.sql, atau row count yang sama saja |
| A-21/V-09 P0 | 01A/01B: middleware CSP report-only ditemukan di source, localhost dev tidak memancarkan header; masih OPEN | Browser Chromium terhadap **staging production-mode**, header report-only, penghimpunan event pelanggaran, cakupan authenticated shell dan keputusan enforce | Header ada di beranda tanpa uji member/admin, nol event hanya pada halaman publik |
| A-23/A-24/V-07 P1 | 01A: 35/35 kombinasi halaman publik, overflow 0 | Uji manual 200% zoom, keyboard/fokus/screen-reader untuk public/account/member/admin | Menganggap nol horizontal overflow = a11y PASS |
| A-07/A-09/A-19/A-27 | 01B: 7 tes/60 assertion keamanan PASS; full regression 170/1415 PASS | Browser staging role nyata dan direct PDF URL/revoke/regrant pada restore | PHPUnit menggantikan UAT staging seluruhnya |

Commit historis terakhir: `9ca5943` (01A) dan `cac9fa6` (01B). Belum ada bukti bahwa pemulihan database atau CSP staging telah dijalankan.

## 2. Isi paket dan pengaman

1. `tools/rel-verify/SGP-REL-VERIFY-01C-DB-RESTORE-COMPARE.php`: default **DRY RUN**; saat `--run` mengeksekusi **SELECT** terhadap **dua koneksi MySQL CLI dengan login-path dan database berbeda**. Membaca identitas database, tabel, kolom, indeks, foreign key/key constraints, daftar migration, dan **COUNT(*) aktual tiap tabel**. Hasil `PASS_COMPARISON_ONLY` atau `FLAG`, **A-25 selalu OPEN**. Tidak mengekspor baris pengguna atau kredensial. Login-path wajib menunjuk akun dengan hak hanya-baca.
2. `tools/rel-verify/SGP-REL-VERIFY-01C-CSP-STAGING-SMOKE.py`: default **DRY RUN**; saat `--run` memakai Chromium Playwright untuk tujuh halaman **publik/guest** (bukan login); menghitung header `Content-Security-Policy-Report-Only`, directive dan event `securitypolicyviolation`, tidak mengambil console body/cookie/screenshot. Jika localhost masih development, hasilnya `BLOCKED_NO_REPORT_ONLY_HEADER` (sesuai ekspektasi). Staging remote memerlukan HTTPS, hostname berlabel `staging`/`stage`/`qa` dan `--ack-staging`.
3. Dokumen ini: prosedur operator, batasan bukti, langkah CMD, acceptance dan pengingat privacy.

Kedua tool hanya menulis laporan teredaksi pada `.siapegawai-backup/rel-verify-01c/*` **jika `--run` dan output valid**. Jangan memindahkan hasilnya ke Git atau menyertakan file dump/PDF/database pengguna.

## 3. Instalasi di Windows CMD

Simpan installer mandiri di root repo, sebagai `siapegawai-update.php` atau nama lengkap yang disertakan. Pastikan HEAD masih tepat baseline dan tidak ada path baru yang telah Anda modifikasi sendiri.

```bat
cd /d E:\NewChapter1\project\siapegawai

git rev-parse HEAD
git status --short --branch

php siapegawai-update.php
php siapegawai-update.php --apply
php siapegawai-update.php --verify

php -l tools\rel-verify\SGP-REL-VERIFY-01C-DB-RESTORE-COMPARE.php
python -m py_compile tools\rel-verify\SGP-REL-VERIFY-01C-CSP-STAGING-SMOKE.py

php tools\rel-verify\SGP-REL-VERIFY-01C-DB-RESTORE-COMPARE.php --help
python tools\rel-verify\SGP-REL-VERIFY-01C-CSP-STAGING-SMOKE.py
```

Installer add-only, SHA-256 source, preflight semua file sebelum menulis, rerun 3 SKIP, konflik ditolak. **Tidak ada migration/seed/restart atau build JS yang diperlukan** untuk toolkit ini.

## 4. A-25: urutan restore terpisah (operasional, tidak otomatis)

**Tahap 4A — Lingkungan**

- Gunakan **staging disposable dengan data dummy**, bukan database aktif/produksi atau dump data pribadi untuk dibagikan di chat.
- Minta DBA membuat `siapegawai_staging_source` (sumber staging, setelah freeze/snapshot) dan **database baru, kosong, terisolasi** `siapegawai_restore_verify_testing` (hasil restore). Verifikasi **host/port**, nama tepat DB, serta bahwa target tidak dipakai aplikasi aktif. Jangan menjalankan `DROP DATABASE` atau overwrite database target.
- Gunakan dua MySQL login-path **berbeda** yang mengarah ke akun `SELECT`-only (`sgp_staging_source_ro`, `sgp_restore_verify_ro`). Konfigurasi dengan `mysql_config_editor` secara interaktif di host operator; **jangan menuliskan password sebagai argumen, dalam chat, atau .md**. Hak backup dan restore DIBEDAKAN dari login-path ini.
- Ambil `mysqldump` dari sumber staging menggunakan opsi `--single-transaction --quick --routines --triggers`, simpan di tempat privat luar web root. Simpan timestamp dan SHA-256 dump sebelum pemindahan. Freeze semua penulisan sumber pada saat inventaris atau gunakan snapshot yang konsisten.
- DBA meninjau dump dan **secara manual** mengimpor ke DB target yang sudah diverifikasi kosong, hanya di host staging terpisah. Hindari dump dengan `CREATE DATABASE`/`USE` menuju sumber. Jangan menjalankan `php artisan migrate:fresh`, `migrate:refresh`, atau `db:wipe` pada aplikasi aktif.
- Copy seluruh `storage/app/siapegawai-class-media` ke **folder fisik berbeda** pada staging restore (bukan symlink/shared storage). Jalankan checker file privat v1.14 terhadap dump asli + folder sumber/restorasi; hasil `PASS_FILE_PARITY_ONLY` tetap bukan A-25 PASS.

**Tahap 4B — Login-path read-only**

Contoh membuat credential **secara interaktif** (operator menyesuaikan host dan user, jangan commit file `.mylogin.cnf`):

```bat
mysql_config_editor set --login-path=sgp_staging_source_ro --host=127.0.0.1 --user=siapegawai_source_reader --password
mysql_config_editor set --login-path=sgp_restore_verify_ro --host=127.0.0.1 --user=siapegawai_restore_reader --password

mysql --login-path=sgp_staging_source_ro --database=siapegawai_staging_source --batch --skip-column-names --execute="SELECT DATABASE()"
mysql --login-path=sgp_restore_verify_ro --database=siapegawai_restore_verify_testing --batch --skip-column-names --execute="SELECT DATABASE()"
```

`--host=127.0.0.1` adalah contoh **hanya bila kedua DB berada pada host staging operator**. Bila restore harus terpisah host/VM, isi alamat sesuai keputusan DBA. Verifikasi user MySQL ini tidak memiliki `INSERT/UPDATE/DELETE/ALTER/DROP/CREATE`; akun ini hanya dipakai untuk perbandingan, bukan proses restore.

**Tahap 4C — Pembanding read-only** (hanya setelah restore nyata selesai)

```bat
php tools\rel-verify\SGP-REL-VERIFY-01C-DB-RESTORE-COMPARE.php --run --source-db=siapegawai_staging_source --restore-db=siapegawai_restore_verify_testing --source-login-path=sgp_staging_source_ro --restore-login-path=sgp_restore_verify_ro --out-dir=.siapegawai-backup/rel-verify-01c/db
```

Jika `mysql` tidak ditemukan di PATH, tambahkan `--mysql-bin=C:\path\ke\mysql.exe` (gunakan path **tepat dan aman**; tidak melalui shell). `--run` akan memeriksa dua nama DB sesuai pola, menolak login-path sama, memeriksa hasil `SELECT DATABASE()` dan membandingkan inventaris. Ketika hasil berbeda, **jangan menormalkan angka atau memodifikasi DB untuk memaksakan PASS**; lakukan diagnosis restore.

Output laporan:

- `REL-VERIFY-01C-DB-COMPARE.md` dan `.json` dalam folder ignored; nama tabel yang tidak cocok diganti identifier hash (bukan data pribadi).
- `PASS_COMPARISON_ONLY`: seluruh nama tabel, struktur kolom, indeks, constraints, migrasi dan jumlah baris cocok **pada waktu query dilakukan**; bukan verifikasi identik isi setiap baris.
- `FLAG`: perbedaan ditemui; tidak layak sign-off.
- `[STOP]`: koneksi/izin/DB/env belum valid; tidak memodifikasi data.

**Tahap 4D — Bukti manual wajib untuk A-25**

- DBA/pemilik staging mencatat **timestamp snapshot/dump, durasi restore aktual (RTO), titik kehilangan data dari snapshot (RPO)** dan log impor yang telah disunting dari rahasia. Jangan mengarang RTO/RPO.
- Pada aplikasi staging **terpisah** yang menunjuk ke DB hasil restore dan folder PDF salinan, login dengan akun dummy: halaman peserta, kelas dan materi, download PDF sah, denial peserta lain/guest, revoke–regrant, hasil CAT, Quiz Kilat dan riwayat/kuota harus konsisten. Pastikan tidak ada request yang diarahkan ke sumber/produksi.
- Bandingkan paritas file dengan checker 01B, termasuk SHA dump yang direkam independen. Kumpulkan keputusan reviewer beridentitas peran (tanpa kredensial atau PII). **Baru setelah semua itu A-25 bisa diusulkan PASS.**

## 5. A-21/V-09: audit CSP di staging production-mode

**Jangan menjalankan `APP_ENV=production` di server Laravel yang dipakai peserta atau di database aktif.** Buat instance staging terisolasi (termasuk MySQL/folder file/APP_KEY/secrets sendiri), aktifkan `APP_ENV=production` hanya pada instance itu dan lakukan `npm run build` di pipeline staging.

Middleware `App\Http\Middleware\AddSecurityHeaders` saat ini hanya menambahkan `Content-Security-Policy-Report-Only` jika `app()->isProduction()`. Karena itu hasil localhost development tanpa header **benar tetapi tidak menyelesaikan A-21/V-09**.

**Tahap 5A — Smoke Chromium public:**

```bat
python -m pip install playwright
python -m playwright install chromium

REM Jalankan hanya bila 127.0.0.1:8001 benar-benar instance staging terisolasi dalam production-mode
python tools\rel-verify\SGP-REL-VERIFY-01C-CSP-STAGING-SMOKE.py --run --base-url=http://127.0.0.1:8001 --out-dir=.siapegawai-backup/rel-verify-01c/csp
```

Untuk staging HTTPS terpisah gunakan contoh (alamat hanya ilustrasi; harus benar-benar resolve ke server staging Anda):

```bat
python tools\rel-verify\SGP-REL-VERIFY-01C-CSP-STAGING-SMOKE.py --run --base-url=https://staging.siapegawai.test --ack-staging --out-dir=.siapegawai-backup/rel-verify-01c/csp
```

Status:
- `BLOCKED_NO_REPORT_ONLY_HEADER`: server tidak mengirim CSP report-only; bila dev mode ini bukan bug otomatis, tetapi **bukan bukti staging**.
- `FLAG_REQUIRES_REVIEW`: kegagalan HTTP/navigasi, header tidak konsisten, header enforce tak diharapkan, atau event pelanggaran terjadi.
- `CANDIDATE_PUBLIC_ONLY`: tujuh halaman publik sudah diuji dan tidak ada pelanggaran yang terdeteksi selama jendela smoke; **A-21/V-09 tetap OPEN**.

Tool melacak jumlah dan **nama directive** event `securitypolicyviolation`, bukan URL sumber yang diblokir, body response, cookie, token, atau isi console. Jangan menyimpulkan **tidak ada violation di seluruh aplikasi** hanya dari tujuh halaman publik.

**Tahap 5B — Review autentikasi dan fitur:** login akun dummy pada staging; periksa secara manual public/account/member/admin shells, Yajra/DataTables, toast, form validasi, font Plus Jakarta Sans, assets Vite, AJAX Quiz Kilat (jawab/next/5 detik), media privat dan error pages. Gunakan DevTools → Console dan Network untuk laporan CSP; redaksi string sumber sensitif. Catat jika directive menghalangi fitur sah. Mode enforce hanya setelah review kebijakan dan sign-off—jangan mengubah middleware sembarang.

## 6. Evidence register dan kriteria sign-off

| Acceptance | Status saat paket dibuat | Kriteria bukti yang harus tersedia |
|---|---|---|
| A-25 | **OPEN P0** | Dump asli diverifikasi; restore DB terpisah; perbandingan 01C; PDF parity 01B; aplikasi dummy pada restore; RTO/RPO; reviewer |
| A-21 | **OPEN P0** | CSP report-only di staging production-mode; pelanggaran nyata direview; jalur authenticated tercakup; keputusan reviewer |
| V-09 | **OPEN P0** | Konsistensi CSP dan keputusan enforce untuk semua shell dan assets, bukan hanya halaman publik |
| A-23/A-24/V-07 | OPEN P1 | Zoom 200%, keyboard focus, screen-reader, mobile/desktop dan shell admin/member |
| REL-VIS-01–09 | OPEN | Screenshot redacted dan sign-off sesuai register 01A |
| OPS-LEGAL | OPEN | Review kebijakan privasi, syarat layanan, WhatsApp operasional, disclaimer nilai |

Satu baris evidence minimum: `acceptance_id`, `observed_at_wib`, `git_sha`, `environment_label`, `operator_role`, `scope`, `expected`, `actual`, `status`, `redacted_evidence_path`, `defect`, `reviewer`, `decision_at_wib`. Jangan menyimpan nama/email peserta, password, log cookie, URL PDF ber-token, `.env`, hasil SQL mentah atau berkas DB dalam Git/chat.

## 7. Kriteria QA toolkit sebelum commit

1. Installer default dry-run menunjukkan **3 ADD, 0 CONFLICT**; apply menghasilkan 3 ADD; verify SHA-256 **3/3**; rerun **3 SKIP**; konflik ditolak sebelum file ditulis; HEAD salah harus ditolak.
2. `php -l` dan `python -m py_compile` PASS; kedua alat `--help`/dry-run berfungsi tanpa koneksi eksternal.
3. Uji proteksi: tool DB menolak nama DB produksi dan login-path sama; tool CSP menolak host remote non-staging tanpa `--ack-staging`.
4. Jika ada staging/restore benar, jalankan smoke sesuai langkah 4 dan 5. **Tidak ada PASS fiktif** jika environment tidak tersedia. PHPUnit aplikasi terakhir: **170 tests, 1.415 assertions** pada v1.14 (bukan hasil v1.15).
5. Git stage **hanya ketiga file** baru. Jangan `git add .`, jangan sertakan `.env.siapegawai.example`, dump SQL, PDF, `.siapegawai-backup`, atau pycache.

## 8. Git setelah verifikasi toolkit

```bash
git status --short --branch
git add -- \
  tools/rel-verify/SGP-REL-VERIFY-01C-DB-RESTORE-COMPARE.php \
  tools/rel-verify/SGP-REL-VERIFY-01C-CSP-STAGING-SMOKE.py \
  docs/qa/REL-VERIFY-01C-INDEPENDENT-RESTORE-AND-CSP-RUNBOOK-2026-11-12.md
git diff --cached --name-status
git diff --cached --check
```

Lakukan commit/push dengan pesan deskriptif hanya setelah QA lokal lulus. SHA commit baru harus dibaca dari output Git; jangan menganggap baseline `cac9fa6` sudah berubah karena installer berhasil. **01C tooling dapat ditutup tanpa menyatakan go-live aman.**
