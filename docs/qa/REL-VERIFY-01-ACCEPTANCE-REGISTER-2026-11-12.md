# Siapegawai — REL-VERIFY-01 Acceptance & Evidence Register

**Tanggal awal:** 12 November 2026 (Asia/Jakarta)  
**Baseline yang harus diverifikasi:** `3cce12ade6d7ff21bb7e349eb2ec5085c14f40e1`  
**Sumber:** `docs/plans/IMPLEMENTATION-PLAN-v2.3.md`, `docs/design/DESIGN-SYSTEM-AND-VISUAL-CONTRACT.md`, `docs/design/VISUAL-ACCEPTANCE-CHECKLIST.md`, `docs/qa/REL-VISUAL-UAT-REGISTER-2026-11-10.md`, dan laporan rekonsiliasi 12 November.  
**Tujuan:** memberikan jejak bukti untuk keputusan rilis; **belum merupakan persetujuan rilis produksi**.

## Tata kelola bukti

- `AUTO-EVIDENCE`: ada tes/build/log yang relevan, namun belum menggantikan UAT nyata.
- `OWNER-ACCEPTED`: persetujuan eksplisit pemilik pada **skenario/layar tertentu**.
- `OPEN`: gate masih memerlukan pemeriksaan atau dokumen.
- `BLOCKED`: environment, akun uji atau konfigurasi staging belum tersedia.
- `FAIL`: pemeriksaan sudah dilakukan dan ditemukan cacat; berikan issue serta tingkat keparahan.
- `PASS`: bukti lengkap untuk *gate itu* sudah tersimpan dan reviewer menyetujuinya. Jangan menandai PASS hanya karena kode atau PHPUnit tersedia.

Setiap baris ditutup hanya jika mempunyai **tanggal, environment/commit, nama skenario, hasil, path bukti yang telah disanitasi, dan reviewer**. File bukti pengujian yang memuat data pribadi, cookie, password, token, kunci jawaban sebelum waktu, atau PDF privat **tidak boleh dipublikasikan**.

## A. Acceptance dari plan v2.3 (`A-01`–`A-27`)

| AC | Yang harus dibuktikan | Status awal | Bukti yang sudah ada / tindakan untuk menutup |
|---|---|---|---|
| A-01 | Navigasi publik valid | OPEN | Automated `SiapegawaiPublicNavigationTest`; crawl semua link tanpa 404, keyboard dan role. |
| A-02 | Demo SKD sembilan soal | AUTO-EVIDENCE | `SiapegawaiDemoTest`; review isi soal dan hasil timeout nyata. |
| A-03 | Bank contoh filter, reset, empty | OPEN | Browser filter semua kategori dan no-result; rekam screenshot 375/1440. |
| A-04 | Registrasi valid → pending | AUTO-EVIDENCE | RegistrationTest; jalankan kasus staging dummy dengan nomor WA nonproduksi. |
| A-05 | Email ganda tidak menduplikasi | AUTO-EVIDENCE | RegistrationTest; uji ulang dengan alamat dummy. |
| A-06 | WA resmi/valid | OPEN | Verifikasi sumber kontak, template pesan dan SOP; jangan membuka nomor produksi dalam laporan. |
| A-07 | Pending hanya boleh aktivasi | AUTO-EVIDENCE | AccountStatusRefreshTest; uji URL peserta langsung. |
| A-08 | Admin approve sekali + audit | AUTO-EVIDENCE | RegistrationTest; uji aksi duplikat via akun superadmin dummy. |
| A-09 | Role/status tidak bisa dinaikkan user | OPEN | Uji manipulasi POST pada akun uji + audit, tanpa menyerang data riil. |
| A-10 | Nonadmin tidak dapat halaman admin | AUTO-EVIDENCE | Admin/participant test; anonymous HEAD + browser role matrix. |
| A-11 | Soal invalid tidak publish | AUTO-EVIDENCE | QuestionManagementTest, QuickQuizEditorialTest. |
| A-12 | Timer server/snapshot soal | AUTO-EVIDENCE | CAT dan QQ tests; uji reload dan clock skew browser. |
| A-13 | Autosave/reload memulihkan sesi | AUTO-EVIDENCE | CAT/QQ tests; uji browser koneksi terputus dan resume. |
| A-14 | Skor sesuai rubrik internal | AUTO-EVIDENCE | CAT/QQ tests; dokumentasi nilai latihan, bukan skor resmi BKN. |
| A-15 | Double submit/timeout idempotent | AUTO-EVIDENCE | QQ race dua proses pernah PASS; ulangi staging sesuai kapasitas. |
| A-16 | Hasil privat milik sendiri | AUTO-EVIDENCE | CAT/QQ ownership tests; uji dua akun dummy berbeda. |
| A-17 | Yajra HTML+JSON satu URL | AUTO-EVIDENCE | DataTables tests; browser search/filter saat mengetik, loading/empty/retry. |
| A-18 | Status kelas/paket jujur | OPEN | Review copy/status, paket Pejuang/Abdi Negara/VIP dan CTA yang benar-benar aktif. |
| A-19 | Materi tanpa enrollment ditolak | AUTO-EVIDENCE | ClassMediaTest; uji direct URL PDF, revoke–regrant dengan pengguna dummy. |
| A-20 | Sesi admin yang dicabut ditolak | AUTO-EVIDENCE | AdminSecurityTest; refresh sesi lama saat role/status berubah. |
| A-21 | CSP report-only→enforce aman | **OPEN — P0** | Test di staging production-mode, catat pelanggaran semua route dan perbaiki sebelum enforce. |
| A-22 | Escaping/XSS/injection | AUTO-EVIDENCE | Test escaping, audit DOM/toast dan dependency advisory. |
| A-23 | Responsive 320–1440 dan zoom 200% | **OPEN — P1** | Screenshot matriks viewport + zoom browser 200%; smoke publik saja tidak cukup. |
| A-24 | Keyboard/focus/ARIA | **OPEN — P1** | Tab/shift-tab, modal, radio, skip link, fokus error, screen reader. |
| A-25 | Backup **dan restore** staging | **OPEN — P0** | Buktikan restore MySQL + file PDF privat ke environment terisolasi; simpan checksum dan log teredaksi. |
| A-26 | WA kosong → state aman | OPEN | Konfigurasi WA staging kosong, cek CTA dan kesalahan registrasi tanpa kebocoran. |
| A-27 | Revoke tanpa menghapus histori | AUTO-EVIDENCE | Tes kelas/paket/QQ; uji revoke–regrant pada browser dan SELECT read-only. |

**Catatan:** status ini berasal dari pelacakan bukti terdahulu. `AUTO-EVIDENCE` bukan penilaian bahwa gate sudah lengkap. Jika source terkini atau hasil uji menyimpang, catat bukti dan ubah baris yang bersangkutan.

## B. Visual/security acceptance (`V-01`–`V-10`)

| AC | Cakupan | Status awal | Kriteria penutupan |
|---|---|---|---|
| V-01 | Beranda setia visual v4 | OPEN | Review halaman utuh 375/1440, nav, ilustrasi, CTA, footer. |
| V-02 | Demo CAT setia v4 | OPEN | State intro, soal, timer, selesai; keyboard dan mobile. |
| V-03 | Bank soal contoh setia v4 | OPEN | Filter pills, pencarian, reset, empty dan layout kecil. |
| V-04 | Katalog kelas/paket jujur | OPEN | Tidak ada harga/kelas/CTA fiktif atau materi privat terbuka. |
| V-05 | Empat shell dipisahkan | AUTO-EVIDENCE | VisualLayoutTest + UAT semua shell. |
| V-06 | URL publik/private tidak tertukar | AUTO-EVIDENCE | Route/authorization tests + URL langsung pada role berbeda. |
| V-07 | Mobile, zoom, accessibility | **OPEN — P1** | Screenshot 320/375/768/1024/1440, zoom 200% dan keyboard. |
| V-08 | Toast / notifikasi aman | OPEN | Copy/kontras, escape HTML, deduplikasi, notifikasi error AJAX. |
| V-09 | CSP aman dengan Vite | **OPEN — P0** | Production-mode CSP report-only evidence dan daftar violation yang ditindaklanjuti. |
| V-10 | Visual kontrak v4, non-generik | OPEN | Per halaman/role; QQ v1.12 owner-accepted untuk layar yang diuji; ruang pembahasan minor diterima. |

## C. Register kelas/media yang masih terbuka (`REL-VIS-01`–`09`)

**Jangan mengarang judul/hasil tiap item.** Register resmi berada di `docs/qa/REL-VISUAL-UAT-REGISTER-2026-11-10.md` pada repository; tabel ini adalah *tracking wrapper* yang harus direkonsiliasi dengan judul dan acceptance spesifik pada dokumen tersebut ketika dieksekusi di Windows.

| ID resmi | Status | Bukti diperlukan | Tanggal / reviewer |
|---|---|---|---|
| REL-VIS-01 | OPEN | Lihat butir 01 register asli; screenshot dan skenario. | — |
| REL-VIS-02 | OPEN | Lihat butir 02 register asli; screenshot dan skenario. | — |
| REL-VIS-03 | OPEN | Lihat butir 03 register asli; screenshot dan skenario. | — |
| REL-VIS-04 | OPEN | Lihat butir 04 register asli; screenshot dan skenario. | — |
| REL-VIS-05 | OPEN | Lihat butir 05 register asli; screenshot dan skenario. | — |
| REL-VIS-06 | OPEN | Lihat butir 06 register asli; screenshot dan skenario. | — |
| REL-VIS-07 | OPEN | Lihat butir 07 register asli; screenshot dan skenario. | — |
| REL-VIS-08 | OPEN | Lihat butir 08 register asli; screenshot dan skenario. | — |
| REL-VIS-09 | OPEN | Lihat butir 09 register asli; screenshot dan skenario. | — |

## D. UAT matriks route & role yang wajib dicatat

| Kategori/route contoh | Guest | Pending | Peserta aktif | Admin operasional | Superadmin |
|---|---|---|---|---|---|
| Publik `/`, `/bank-soal`, `/kelas`, `/paket` | Boleh lihat | Boleh lihat | Boleh lihat | Boleh lihat | Boleh lihat |
| Profil dan riwayat peserta | Tolak/redirect | Tolak/redirect | Hanya milik sendiri | Tidak otomatis boleh | Tidak otomatis boleh |
| `/quiz-kilat` dan attempt pribadi | Tolak/redirect | Tolak/redirect | Boleh sesuai paket/kuota | Tolak | Tolak |
| Kelas saya/media privat | Tolak/redirect | Tolak/redirect | Hanya enrollment aktif | Tidak otomatis boleh | Tidak otomatis boleh |
| `/admin/*` | Tolak | Tolak | Tolak | Sesuai permission | Sesuai permission |
| Kuota paket / penetapan paket peserta | Tolak | Tolak | Tolak | Tolak jika superadmin-only | Sesuai policy |

Setiap pemeriksaan memakai **akun dummy dan fixture terisolasi**, bukan data peserta riil. Catat status code, tampilan, kebocoran detail, dan respons saat akun dibekukan/dicabut. Tidak cukup hanya melihat item menu disembunyikan.

## E. Bukti yang harus diisi oleh operator (template)

```text
Acceptance ID:
Environment: local / staging (nama non-sensitif)
Git SHA:
APP_ENV: development / staging production-mode (tanpa isi .env)
Akun/role dummy:
Route atau skenario:
Viewport / zoom:
Expected:
Observed:
Result: PASS / FAIL / BLOCKED
Artifact path (redacted):
Defect ID and severity (if FAIL):
Reviewer and date:
```

## F. Defect register awal

| Defect | Severity | Status | Dampak | Tindakan |
|---|---|---|---|---|
| REL-P0-01 — Bukti restore DB + private media belum ada | P0 **gate**, belum terbukti sebagai bug | OPEN | Tidak ada jaminan recovery saat kehilangan data | Lakukan rehearsal staging, verifikasi query dan PDF, dokumentasikan RTO/RPO aktual. |
| REL-P0-02 — CSP production-mode belum diaudit menyeluruh | P0 **gate**, belum terbukti sebagai bug | OPEN | Enforcement belum aman untuk dirilis | Kumpulkan report-only violation dari semua shell, asset dan AJAX. |
| REL-P1-01 — Bukti visual/keyboard/zoom lintas modul belum lengkap | P1 gate | OPEN | Hambatan UX/aksesibilitas dapat lolos | Jalankan browser smoke dan manual UAT per role. |
| REL-P2-01 — Ruang kosong panel pembahasan QQ | P2 | **ACCEPTED** | Kosmetik, pemilik produk menerima | Tidak perlu patch pada REL-VERIFY-01. |

**Keputusan rilis saat register dibuat:** **NO-GO / NOT YET VERIFIED untuk produksi**, bukan karena fitur inti gagal, melainkan **dua gate P0 belum mempunyai bukti acceptance**.
