# Siapegawai — REL-VERIFY-01 Read-only Audit & Release Gate Runbook

**Milestone:** REL-VERIFY-01 (Tahap pembuktian lintas modul)  
**Baseline Git:** `3cce12ade6d7ff21bb7e349eb2ec5085c14f40e1`  
**Status saat penyerahan:** TOOLING PREPARED; LIVE VERIFICATION/PRODUCTION SIGN-OFF NOT PERFORMED  
**Jenis perubahan:** hanya dua alat baca dan dua dokumen QA; tidak mengubah route, view, migration, CAT, kelas, Quiz Kilat, DB, atau paket.

## 1. Hubungan dengan plan

Mengimplementasikan persiapan eksekusi rekomendasi **`REL-VERIFY-01`** dari laporan implementasi 12 Nov, khususnya inventaris `A-01`–`A-27`, `V-01`–`V-10`, register `REL-VIS-01`–`09`, keamanan/CSP, aksesibilitas/responsif, backup-restore dan keputusan go/no-go.

**Batas yang sengaja dipertahankan:** `QQ-CORE-02 v1.12` telah diterima untuk UX yang diperlihatkan; tidak ada pemolesan ulang. Lulus PHPUnit 163/1.355, Chromium terisolasi, dan MySQL race dari tahap sebelumnya **tidak** dijadikan bukti restorasi/production release.

## 2. Komponen paket

| File | Fungsi | Mengubah aplikasi? |
|---|---|---|
| `tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php` | Pemeriksaan marker middleware, CSP, cache privat, kesiapan route data, HTTP HEAD tanpa login di localhost | Tidak; hanya menulis hasil audit ke folder bukti setelah `--run` |
| `tools/rel-verify/SGP-REL-VERIFY-01-PUBLIC-BROWSER-SMOKE.py` | Screenshot publik 320/375/768/1024/1440 dan pengamatan overflow/semantik HTML awal | Tidak; hanya GET halaman publik tanpa login |
| `docs/qa/REL-VERIFY-01-ACCEPTANCE-REGISTER-2026-11-12.md` | Matriks semua acceptance dan severity | Tidak |
| Dokumen ini | Prosedur pengujian, interpretasi, gate dan handover | Tidak |

Installer standalone hanya melakukan **ADD**, memeriksa baseline dan SHA-256 target serta menolak file konflik sebelum menulis. Backup lama tidak diperlukan jika semua target belum ada; installer juga membuat manifest pemasangan dan mendukung rerun `SKIP`.

## 3. Jalankan inspeksi awal (Windows/Git Bash)

```bash
cd /e/NewChapter1/project/siapegawai

git rev-parse HEAD
git status --short --branch

# Default dry run, tanpa filesystem/network:
php tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php
python tools/rel-verify/SGP-REL-VERIFY-01-PUBLIC-BROWSER-SMOKE.py
```

**Pastikan SHA sama dengan baseline yang tercantum.** Jika Git sudah memiliki commit lain, audit dengan sengaja menandai perubahan baseline. Jangan mengembalikan kode demi meloloskan alat.

## 4. Audit statis dan route

Dari terminal Git Bash (atau CMD dengan quoting yang sesuai):

```bash
# Simpan bukti di folder yang diabaikan Git, bukan docs/qa.
mkdir -p .siapegawai-backup/rel-verify-01

php artisan route:list --json > .siapegawai-backup/rel-verify-01/routes.json

php tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php --run \
  --route-json=.siapegawai-backup/rel-verify-01/routes.json \
  --out-dir=.siapegawai-backup/rel-verify-01/static
```

Artefak: `REL-VERIFY-01-RESULTS.md` dan `.json` pada direktori output. `OBSERVED` hanya berarti marker ditemukan dalam source; `PASS` untuk auth route berarti metadata berisi guard yang diharapkan, **bukan** penetrasi otorisasi. `FLAG` harus diteliti, `BLOCKED` memerlukan bukti tambahan.

**Keamanan data:** alat tidak membaca `.env`, tabel user, session cookie, token atau body HTTP. *Jangan unggah* output `.env`, database SQL asli atau header `Set-Cookie` ke repository.

## 5. Audit live HTTP localhost dan browser publik

Jalankan `php artisan serve` di terminal lain; aplikasi harus dapat diakses tanpa port forwarding publik. Kemudian:

```bash
php tools/rel-verify/SGP-REL-VERIFY-01-READONLY-AUDIT.php --run \
  --base-url=http://127.0.0.1:8000 \
  --route-json=.siapegawai-backup/rel-verify-01/routes.json \
  --out-dir=.siapegawai-backup/rel-verify-01/http

# Opsional; butuh pip install playwright dan python -m playwright install chromium.
python tools/rel-verify/SGP-REL-VERIFY-01-PUBLIC-BROWSER-SMOKE.py --run \
  --base-url=http://127.0.0.1:8000 \
  --out-dir=.siapegawai-backup/rel-verify-01/browser
```

Alat Chromium menguji **halaman publik saja**. Bila font/visual memerlukan Vite build terbaru, jalankan `npm run build` dahulu. Screenshot dan hasil semantik/overflow perlu dinilai manusia terhadap Visual Contract. Ukuran viewport 200% **belum** diuji oleh script; jalankan browser zoom 200% manual. Jangan menyebut screenshot otomatis sebagai PASS visual final.

**Catatan keamanan:** HTTP probe sengaja dibatasi ke loopback `http`; tidak mengirim kredensial dan tidak mengikuti redirect. Route privat yang mengembalikan 302 membutuhkan pemeriksaan Location secara manual bahwa menuju login; 404 bukan bukti policy sudah benar. CSP `report-only` yang hanya aktif pada `APP_ENV=production` harus diuji di **staging production-mode**, bukan menyimpulkan dari localhost yang default tidak menampilkan CSP.

## 6. Gate UAT autentikasi/role & media privat

Gunakan akun dummy: guest, pending, peserta aktif A, peserta aktif B, operational_admin, superadmin; siapkan kelas dummy, PDF privat, enrollment dan material v1/v2. Jalankan urutan:

1. Login peserta A, buka materi kelas dan PDF privat, catat hasil HTTP dan checksum file uji (bukan file pengguna nyata).
2. Cabut enrollment A lewat superadmin. Ulangi GET PDF lama dengan URL yang sama dalam sesi A; harus ditolak. Uji juga URL/file milik kelas lain dan peserta B.
3. Regrant A; histori completion lama tetap ada dan izin PDF kembali sesuai kebijakan. Jangan menghapus progress.
4. Terbitkan revisi materi v2, lihat sebagai A; materi v1 dan progres terpin tetap benar menurut kontrak S7.5.
5. Di monitoring admin, cari/filter AJAX pada **URL yang sama** dengan halaman index; cek batas kelas/role.
6. Coba URL admin dengan peserta, dan URL participant dengan guest/pending/admin; jangan hanya mengandalkan menu tersembunyi.
7. Uji Quiz Kilat pada paket dummy dengan kuota **uji yang disetujui di database terisolasi**, catat sisa kuota hari ini sebelum/start/resume/selesai; jangan membuat policy kuota produksi palsu.

**Kondisi gagal:** respons 200 untuk materi privat setelah revoke, histori orang lain terlihat, progress terhapus tanpa ketentuan, token/kunci soal bocor sebelum lock, double-debit, atau redirect berulang. Prioritaskan P0 untuk kebocoran kontrol akses/data.

## 7. Gate responsive dan accessibility

Audit **320, 375, 768, 1024, 1440 px**, lalu browser **zoom 200%** secara nyata. Untuk tiap shell yang masuk rilis: public, account, participant, admin. Periksa horizontal overflow, nav, state tabel Yajra, opsi soal/feedback, scroll modal, label error, keyboard Tab/Shift+Tab/Enter/Space, focus visible, contrast, semantik heading, notifikasi, pembaca layar bila tersedia. Catat artifact redacted per AC.

## 8. P0 — Backup + restore staging yang **terpisah**, bukan sekadar dump

Tidak ada tool dalam paket ini yang menjalankan `mysqldump`, `mysql`, migrasi, file deletion, atau update database. Eksekusi dilakukan operator pada **staging disposable** dengan kredensial backup read-only dan restore write terpisah.

**Checklist prosedural:**

1. Pastikan lingkungan staging dan target restore **bukan** database aplikasi kerja/produksi. Buat database restore bernama eksplisit, misalnya `siapegawai_restore_testing`, pada instans MySQL yang tepat. Catat host/port/database **tanpa password**.
2. Ekspor snapshot MySQL dari staging dengan fasilitas backup administrasi yang aman; ekspor data file privat yang sesuai (PDF/materi) dari `storage/app/private` menggunakan ACL terbatas. Perhatikan konsistensi transaksi dan media saat snapshot.
3. Verifikasi **checksum SHA-256** dump dan berkas privat, ukuran, waktu, serta waktu mulai/selesai. Simpan hanya hash/metadata dalam evidence, tidak file dump atau data pengguna.
4. Impor snapshot **hanya** ke database restore terisolasi. Putuskan semua koneksi aplikasi nyata dan jangan arahkan konfigurasi runtime utama ke database restore. Gunakan konfigurasi terpisah untuk sesi verifikasi.
5. Lakukan SELECT read-only yang membandingkan hitungan baris tabel penting dengan source snapshot, status migration, 1 contoh attempt QQ dengan nomor anonim, histori Latihan Kelas, dan file PDF fixture yang diakses dengan otorisasi saat runtime pemulihan.
6. Ukur durasi restore aktual (RTO terukur) dan selisih data antara snapshot dan pemulihan (RPO terukur). Catat error apa pun dan ulangi bila perlu; jangan mengklaim tujuan RTO/RPO jika belum disetujui secara bisnis.
7. Hapus dump/backup sensitif sesuai kebijakan retensi melalui prosedur administrasi. Jangan menyimpan kredensial di Git, argumen CLI yang terekam shell, maupun screenshot.

**PASS A-25** hanya setelah restore dibuktikan dengan environment, checksum, row count, media dan reviewer. Cadangan `.siapegawai-backup` dari installer adalah backup **source**, **bukan** restore database.

## 9. P0 — CSP dan browser security

CSP pada kode memakai `Content-Security-Policy-Report-Only` untuk production-mode. Untuk staging, gunakan domain dummy dengan HTTPS dan konfigurasi **terpisah dari produksi**. Periksa DevTools Console/Network → CSP violations untuk halaman publik, akun, admin, member, AJAX, fonts/asset Vite, PDF. Bukti minimal: header yang benar, daftar violation (kosong/ditangani), keputusan apakah siap enforce. **Tidak** otomatis mengubah report-only menjadi enforce dan tidak memasang `unsafe-inline`/`unsafe-eval` demi menghilangkan warning.

Audit vulnerability dependency di lingkungan operator melalui `composer audit` dan `npm audit --omit=dev` (hasil dicatat lengkap dengan versi dan triage). Penilaian severity tidak boleh hanya berdasarkan angka; lihat apakah paket/route benar-benar terpapar.

## 10. Regression gate setelah temuan (tanpa perubahan fitur saat audit)

```bash
php artisan test
npm run build
php artisan view:cache
php artisan route:list
```

Angka historis sampai baseline: **163 tests / 1.355 assertions**; bukti itu berasal dari log Windows tahap QQ v1.12, **bukan hasil tool ini**. Jika ditemukan bug, buat **hotfix terisolasi**, jalankan regresi ulang, simpan delta dan output uji; jangan buka CAT/QQ/kelas kembali tanpa temuan nyata.

## 11. Kebijakan keputusan dan penyelesaian tahap

- `NO-GO / NOT VERIFIED` selama P0 A-21 (CSP) atau A-25 (restore) masih OPEN/BLOCKED, meskipun semua test otomatis lulus.
- Setelah P0 ditutup, telaah P1 accessibility/mobile/data leakage dan operasi. Pemilik produk memilih cakupan rilis; jangan berasumsi paket komersial penuh sudah siap karena kuota QQ tersedia.
- Untuk mengakhiri `REL-VERIFY-01`: register lengkap, bukti staging redacted, review isu P0/P1/P2, serta sign-off keputusan go/no-go beserta SHA/environment yang dinilai.
- **Jangan** meng-commit evidence sensitif dari `.siapegawai-backup`. Dua tools dan dua dokumen QA adalah artefak yang boleh dicatat ke Git setelah diperiksa, bukan bukti release sign-off.

## 12. Handover

**Yang diserahkan sekarang:** audit runner source+anonymous HTTP, browser public smoke, register A/V/REL-VIS, runbook staging. **Yang belum dieksekusi:** localhost aplikasi operator, login multi-role, staging restore, CSP production-mode, 200% zoom, legal/WA sign-off. **Keadaan milestone:** `REL-VERIFY-01 — TOOLING READY / EVIDENCE COLLECTION OPEN / PRODUCTION NO-GO`.
