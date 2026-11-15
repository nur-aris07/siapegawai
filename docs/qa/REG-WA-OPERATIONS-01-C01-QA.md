# REG-WA-OPERATIONS-01 — Fase C / C-01 QA

**Keputusan:** Registrasi peserta GRATIS auto-active (Fase B REV-02), tanpa approval atau WhatsApp.  
**Baseline Git:** `4948a60ede6b08ed38cfa1eb5b1d16a5a773eb39`.  
**Status:** Patch tersedia; verifikasi pada environment owner belum dijalankan.  
**Bukan release sign-off.**

## Dampak teknis

- `RegistrationController`: gate `registration_enabled + privacy_approved + terms_approved + privacy_version` yang sah; tidak bergantung nomor WhatsApp resmi. POST tervalidasi melakukan 1 transaksi user `participant/active`, registration request `active`, consent, basic `member` grant dan audit sistem (actor NULL). Login dan session regeneration terjadi setelah commit; 303 ke dashboard.
- `RegistrationActivationService`: **TIDAK DIUBAH**; hanya bekerja pada akun historis `pending` dan mempertahankan manual confirmation serta audit lama.
- Paket: **TIDAK DIUBAH**; `member` bukan `member_plan`, fallback `PEJUANG` tetap berlaku, QQ tanpa policy valid fail-closed, enrolment Kelas tetap wajib.
- UI: Form gratis dan halaman admin menampilkan terminologi auto-active; tidak mengklaim konfirmasi WA/payment. Old WhatsApp endpoint hanya untuk legacy pending; peserta aktif dialihkan ke dashboard.
- Tidak ada migration, perubahan `.env`, mutasi database, setelan kuota/harga, kirim email/WA, pembayaran atau Git otomatis.

## Quality gate pelaksanaan

1. Pastikan Git baseline `main` dan `origin/main` sesuai SHA, tidak ada staged atau perubahan tracked di luar manifest. `siapegawai-update.php` dan `.siapegawai-backup` tetap diabaikan Git.
2. `php siapegawai-update.php` -> `--apply` -> `--verify`; seluruh 8 manifest hash terverifikasi, backup aman dan idempotent.
3. `php artisan optimize:clear`, lint PHP, `php artisan test tests/Feature/SiapegawaiRegistrationTest.php`.
4. `php artisan test`, `npm run build` bila diperlukan, `git diff --check`, `git status --short --branch`.
5. Browser UAT di lingkungan simulasi **bukan** DB produk tanpa izin legal: (a) form gratis; (b) daftar → aktif/login/dashboard; (c) admin list/detail menunjukkan aktivasi otomatis; (d) pending historis tetap aman; (e) tidak ada premium/QQ unlimited/Kelas enrollment otomatis.

## Batasan / external gate

- **Jangan mengaktifkan registrasi publik** sebelum dokumen privasi dan syarat layanan telah disahkan. Variabel `SIAPEGAWAI_REGISTRATION_ENABLED`, `SIAPEGAWAI_PRIVACY_APPROVED`, `SIAPEGAWAI_TERMS_APPROVED`, `SIAPEGAWAI_PRIVACY_VERSION` dibiarkan apa adanya.
- Nomor **peserta** tetap wajib di form baseline; nomor **resmi pengelola** bukan prasyarat. Perubahan data minimisasi dan verifikasi email adalah milestone lain.
- Akun pending lama tidak dimigrasi massal. CSP staging, backup/restore staging, anti-bot/rate monitor dan sign-off release tetap OPEN.

**Acceptance:** hanya setelah focused + full tests, UAT visual dan owner memberi `OWNER-ACCEPTED REG-WA-OPERATIONS-01`. Baru lanjut E Git manual.

## C-02 — Uji gate legal terisolasi dari rate-limit (15 November 2026)

**Temuan owner C-01:** satu focused test gagal (`Expected 503, received 429`) pada iterasi ke-6 dalam satu menit. Middleware POST `/daftar` memang memiliki `throttle:5,1`; ini bukan bukti gate legal menerima registrasi dan bukan alasan melonggarkan limit produksi.

**Koreksi C-02:** hanya mengubah `SiapegawaiRegistrationTest` dengan alamat IP penguji berbeda untuk setiap kondisi legal (alamat dokumentasi `192.0.2.0/24`), agar setiap permintaan mencapai gate 503. Menambah regresi khusus yang mengirim enam percobaan dari satu IP, memverifikasi percobaan 1–5 menghasilkan 503 ketika registrasi ditutup dan percobaan ke-6 menghasilkan 429. Tidak ada perubahan controller, middleware, kuota, route, database, atau logika bisnis.

**Verifikasi setelah apply:** manifest 8/8; focused test termasuk kedua skenario di atas; full PHPUnit; `git diff --check`. **Tidak boleh mengklaim PASS sampai output owner diterima.** Semua batasan dan gate legal C-01 tetap berlaku.