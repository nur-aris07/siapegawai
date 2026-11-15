# AUTH-GOOGLE-01 — Fase C C-01 QA / 15 November 2026

Baseline Git: `6bdf13e2d782e8a2de2081d3b1e94dae0e1807d3`.

## Implementasi

- Google OAuth via **Laravel Socialite** stateful (bukan `stateless()`), hanya participant; Google subject yang unik disimpan tanpa token.
- Tidak auto-link berdasarkan email; existing participant harus login lokal dan memverifikasi password sebelum linking.
- Akun Google baru memerlukan WA peserta, consent, dan gate legal yang masih berlaku; aktivasi langsung GRATIS PEJUANG.
- MySQL migration additive `user_identities` dibuat, **installer tidak menjalankannya**. Tidak ada perubahan database aktif.
- Ketika `SIAPEGAWAI_GOOGLE_AUTH_ENABLED` false atau credential belum disiapkan, Google CTA disembunyikan dan route fail-closed.
- `composer.json` **dan** `composer.lock` dihasilkan bersama oleh Composer sungguhan melalui perintah operator `composer require laravel/socialite:^5.0`, **bukan** direkayasa installer. Jangan commit sebelum keduanya sesuai dan test PASS.

## Acceptance & blockers

1. [ ] Installer dry-run/apply/verify PASS; idempotensi dan baseline SHA dijaga.
2. [ ] `composer require laravel/socialite:^5.0` berhasil pada PHP 8.3 operator; composer.lock harus real.
3. [ ] PHPUnit terisolasi memakai `DB_DATABASE=siapegawai_testing`; full suite PASS.
4. [ ] Migration testing sukses via RefreshDatabase; **jangan migrate database aktif** untuk sekadar QA.
5. [ ] OAuth disabled/invalid state/unverified email dan email collision ditolak; tidak auto-link.
6. [ ] New user -> onboarding WA/consent -> participant active, basic member grant, PEJUANG, tanpa premium/QQ/Kelas otomatis.
7. [ ] Existing participant active -> login Google; suspended/pending/admin ditolak.
8. [ ] Manual linking step-up password + email exact-match + audit; concurrent duplicate subject dibatasi index DB.
9. [ ] Vite, git diff check, mobile/desktop UI PASS.
10. [ ] Real Google Cloud OAuth client web application, callback origin tepat, trial browser end-to-end. **BLOCKED-GOOGLE-CREDENTIALS** sampai credential owner tersedia.
11. [ ] Migrasi database aplikasi nyata menunggu backup, approval dan langkah operator terpisah; *release P0 tetap OPEN*.

**Catatan keamanan:** jangan kirim client secret, access token, OAuth code, password, atau tautan reset melalui chat/log. Jika user Google-only membutuhkan login email, gunakan password reset milik sendiri. Sesi onboarding 10 menit.

## C-02 — Penyelarasan tombol Google (UI saja)

- Login: tombol **Masuk dengan Google** berlebar 100% mengikuti tombol **Masuk**, dipisahkan teks **ATAU** dan garis horizontal.
- Daftar: tombol **Daftar dengan Google** diletakkan di bawah form supaya sejajar lebar dengan tombol **Daftar GRATIS**.
- Ikon G empat warna diinline sebagai SVG statis (tanpa network, JavaScript, CSS eksternal, atau inline event handler), dekoratif `aria-hidden`.
- CSS hanya pada `.shell-account`; focus-visible, hover, dan mobile mengikuti layout responsif yang ada.
- Google route, OAuth credentials, migration, database, login password, consent, dan hak paket **tidak diubah**.
- UAT: pastikan tombol tampil ketika Google service siap, tersusun full-width desktop/375px, dan tidak muncul ketika disabled. OAuth nyata tetap harus diuji oleh owner.
- Setelah revisi: jalankan focused Google test, full PHPUnit, npm run build, git diff --check.
- **Database `siapegawai` aktif telah dimigrasi secara manual oleh owner hingga batch 16**, berdasarkan log owner 15 November; installer C-02 tidak melakukan SQL. Sebelum rilis perlu backup/restore terverifikasi dan legal/infra approval.


## C-03 — Matriks hasil tindakan Google dan feedback (2026-11-15)

- Akar masalah UAT: callback sudah memakai `with('warning')`, tetapi komponen toast hanya merender `success` dan `status`; error Google tak pernah ditampilkan.
- **Login tertaut aktif**: dashboard 303 + pesan sukses.
- **Identitas Google baru**: halaman onboarding yang meminta data dan consent; sukses 303 dashboard + konfirmasi Pejuang, gagal validasi tetap di form dengan error.
- **Email sudah ada, Google belum tertaut**: login dengan panel *HASIL LOGIN GOOGLE* berisi alasan, langkah login password / lupa kata sandi / Profil saya → Hubungkan Google; **tidak** auto-link.
- **Google dibatalkan / sesi OAuth hilang atau kedaluwarsa / jaringan atau token gagal / email tidak terverifikasi / registrasi ditutup / akun linked tidak aktif**: panel login dengan penyebab umum dan tindakan ulang; tidak membocorkan provider token atau status rahasia.
- **Penautan akun terautentikasi**: salah password = validasi, batal/gagal/identitas lain = tetap di Profil dengan pesan; berhasil = Profil dengan pesan sukses. Tidak diarahkan ke halaman guest login saat link gagal.
- **Onboarding expired atau izin legal ditutup di tengah proses**: kembali ke login dengan penjelasan, tanpa membuat akun.
- Komponen `toast-region` kini merender flash `warning` dan `error` selain `success` / `status`; semua melalui Blade escaped text dan JavaScript textContent, tanpa JS inline.
- Provider disabled tetap 503 dan tombol tidak muncul; 403 untuk nonce salah, 419 CSRF, 429 throttle tetap tidak berubah, middleware keamanan tetap aktif.
- Manual UAT minimum: collision akun peserta existing, cancel Google, akun baru sukses, akun linked sukses, link wrong password, link Google email salah, provider failure/expired, legal ditutup, blocked account. Browser tidak perlu mengakses DB testing untuk OAuth riil.
- Tidak ada SQL, migration, dependensi, kredensial, entitlement, atau Git otomatis dalam installer C-03.

- Email akun admin/pending/suspended/rejected atau sudah tertaut Google berbeda tidak disarankan menuju Profil peserta; tampilkan "Akun tidak dapat ditautkan otomatis" dan CTA ke bantuan pengelola.
