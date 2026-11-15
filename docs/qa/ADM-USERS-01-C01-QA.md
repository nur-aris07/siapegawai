# ADM-USERS-01 — C-01 / QA & UAT

**Baseline:** `72b5db2deb14be20784b4ae842bbbab8032c7811` (`main`).
**Status:** NOT OWNER-ACCEPTED / UAT diperlukan.

## Perubahan

- Superadmin aktif menambah `participant/pending` melalui `/admin/peserta/tambah`; operasional admin/guest/peserta tidak dapat membuat peserta.
- Password awal acak di-hash dan tidak pernah diberikan kepada admin. Password broker mengirim link lewat mailer existing; kegagalan email tidak mengaktifkan akun. Superadmin dapat meminta kirim ulang dengan throttle dan batas broker.
- Reset password valid menulis audit `participant.invitation_password_set`. Login pending menuju `/aktivasi`; consent peserta pada versi kebijakan yang benar mengaktifkan akses GRATIS dan satu grant `member`.
- Catatan undangan berasal dari audit; tidak dipalsukan menjadi `RegistrationRequest`. Akun pending web historis masih memakai jalur lama.
- Tidak ada grant premium, QQ quota gratis baru, enrollment, migration, update `.env`, atau perubahan Git oleh installer.

## QA otomatis (WAJIB setelah apply)

1. Installer `--dry-run`, `--apply`, `--verify`, idempotensi. Hash dan staging baseline harus cocok.
2. `php artisan optimize:clear` dan `php artisan view:cache` (kemudian `view:clear`).
3. `php artisan test tests/Feature/SiapegawaiAdminInvitationTest.php`; lalu seluruh `php artisan test` dengan database **testing** (bukan aktif).
4. `npm run build`, `git diff --check`, dan `git status --short --branch`.
5. UAT: Superadmin → Direktori Peserta → Tambah Peserta; email via mailbox uji privat; set password via link diterima; login pending → `/aktivasi`; consent → dashboard sebagai PEJUANG. Uji role operasional tidak dapat create/resend; legal blocked; enroll/QQ policy; keyboard/mobile.

## Gate dan keterbatasan

- Reset link diterima mailer (`Password::RESET_LINK_SENT`) bukan jaminan peserta menerima email. Untuk UAT end-to-end gunakan mailbox uji yang sungguh dioperasikan dan mailer terisolasi. Jangan menyalin token ke chat, log, URL publik, atau admin. Jika tidak tersedia, tandai **BLOCKED-MAIL-DELIVERY**.
- Approval dokumen legal masih wajib untuk consent onboarding; `registration_enabled` untuk registrasi publik tetap terpisah.
- Tests/fungsi lokal bukan release staging signoff. `REL-CSP-STAGING-01`, recovery independen, legal, dan operasi email masih OPEN.

## C-02 — koreksi preflight anchor

- C-01 gagal sebelum apply karena tiga anchor pada view `resources/views/admin/participants/show.blade.php` memakai indentasi terlalu dalam satu spasi dibanding source Git pada baseline `72b5db2`.
- C-02 menyesuaikan anchor pada view itu dengan source sebenarnya. Tidak ada perubahan scope, data, role, atau business logic.
- Jalankan `--dry-run`, `--apply`, `--verify`, focused PHPUnit, full PHPUnit, Vite dan UAT. Jangan gunakan `--apply` bila ditemukan `[STOP]`/`CONFLICT`.

## C-03 — determinisme pengujian gate legal undangan

- C-02 apply dan verify 12/12 PASS. Focused test menghasilkan 4 PASS dan 1 FAIL pada `broker_password_proof_consent_and_free_activation_are_owner_only` karena mengasumsikan konfigurasi legal lingkungan bernilai false; lingkungan UAT dapat sudah mengaktifkan flag privasi/syarat layanan untuk registrasi gratis.
- C-03 **hanya** memperbaiki skenario test (dan catatan QA). Untuk pengujian password proof, set legal approved secara eksplisit agar kegagalan disebabkan belum memiliki bukti reset password. Untuk pengujian legal fail-closed, set flag legal false serta `privacy_version=DRAFT` secara eksplisit; pastikan akun tetap pending tanpa consent maupun grant. Setelah itu baru aktifkan legal simulasi untuk memastikan onboarding dan paket Pejuang berhasil.
- Gate legal runtime, throttle, route, controller, service, status akun, email, akses premium, dan database **tidak berubah**.
- Harus diuji di Windows owner: focused PHPUnit, full PHPUnit, build Vite, lalu UAT dengan email uji yang benar-benar terkirim. Mailer log/array tidak membuktikan pengiriman; jangan masukkan token ke laporan/chat.

## C-04 — visual layout test independen dari konfigurasi legal UAT

- C-03: installer 12/12 PASS dan focused invitation test 5 PASS / 71 assertions. Full suite 200 PASS, 1 FAIL di `SiapegawaiVisualLayoutTest::test_login_and_registration_use_account_shell()` karena mengharapkan `Registrasi belum dibuka` ketika konfigurasi legal lokal sudah disahkan dan `/daftar` menampilkan formulir GRATIS.
- C-04: test visual secara eksplisit memeriksa **kedua** state: registration/legal tertutup menunjukkan pesan *Registrasi belum dibuka*; registration/legal disetujui menampilkan tombol *Daftar GRATIS*; keduanya wajib tetap memakai account shell, tanpa `site-nav` marketing.
- Tidak ada perubahan terhadap route, controller, layanan undangan, business logic, `.env`, middleware, throttle, gate legal produksi, skema database, maupun data peserta. Hanya test visual dan catatan QA yang berubah.
- Owner: lakukan `--dry-run`, `--apply`, `--verify`; focused visual dan invitation tests, full PHPUnit, Vite build, lalu UAT admin invite dan email mailbox nyata. Jangan commit sebelum OWNER-ACCEPTED.
