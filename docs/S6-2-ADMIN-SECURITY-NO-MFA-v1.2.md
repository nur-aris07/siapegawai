# Siapegawai S6.2 v1.2 — Keamanan Admin Tanpa OTP/MFA

## Keputusan produk

Login tetap email + kata sandi, tanpa OTP, TOTP atau recovery codes. Tidak ada pengaturan atau dependency MFA. Pembatasan login lama tetap dipertahankan (bukan ditambah ulang).

## Pengamanan

1. Admin Operasional dan Superadmin memperoleh halaman `/admin/keamanan` untuk mengganti password sendiri, dengan verifikasi password saat ini dan aturan minimal 12 karakter yang mengandung huruf dan angka.
2. `users.admin_security_version` dimulai pada 0. Setelah ganti password atau reset password admin, nilainya bertambah satu. Login admin menyimpan versi ini di session.
3. Middleware setiap route admin membandingkan versi dari session dengan versi terbaru dari DB. Versi berbeda -> logout/invalidate session dan redirect login. Sesi lama yang tidak memiliki penanda (pra-v1.2) hanya dapat digunakan sampai versi pertama kali naik dari 0.
4. Audit login admin berhasil, gagal (untuk email admin yang sudah ada), ditolak, perubahan password sendiri, serta reset password. Password dan token tidak pernah direkam di audit. IP disimpan sebagai HMAC pendek, bukan alamat mentah.
5. Password reset admin juga menaikkan versi sesi dan merotasi remember token, sehingga sesi admin sebelum reset tidak dapat melanjutkan akses admin.
6. Akses terhadap halaman ini dibatasi `auth` + `EnsureActiveAccount` + `EnsureRole` + `EnsureAdminSessionVersion`; POST perubahan password dibatasi `throttle:5,1`.

## Pemasangan

Simpan `siapegawai-update-v1.2.php` di root Laravel (boleh dinamai `siapegawai-update.php`). Jalankan `php siapegawai-update.php` untuk dry run, lalu `php siapegawai-update.php --apply --migrate --verify`. Backup MySQL terlebih dahulu. Installer tidak menjalankan rollback otomatis terhadap migrasi.

## Pengujian browser

- Login Superadmin lalu buka `/admin/keamanan`.
- Isi password lama yang salah -> ditolak, tidak mengganti password.
- Isi password lama yang benar dan password baru yang valid -> dialihkan ke login, lalu login dengan password baru.
- Tab/perangkat admin yang masih menyimpan sesi sebelum password berubah -> ditolak dan diarahkan login saat mengakses halaman admin.
- Participant tidak boleh membuka `/admin/keamanan`.

## Catatan keamanan dan keterbatasan

- Ini bukan pengganti MFA pada layanan produksi. Keputusan menghilangkan MFA diterapkan sesuai kebutuhan prototype/development. Untuk rilis publik, perlu peninjauan risiko dan perlindungan operasional.
- Sesi yang tidak memiliki stamp dan berada pada `admin_security_version=0` adalah sesi lama yang didukung sementara. Setelah satu kali perubahan/reset password, semua sesi tanpa stamp ditolak.
- Mekanisme ini mencabut *akses ke route admin* pada permintaan berikutnya. Logout global seluruh aktivitas user di luar route admin tidak diklaim.
- Karena pembatasan dilakukan saat request berikutnya, halaman admin yang sudah ter-render sebelumnya tidak otomatis menghilang dari tab lain.
- Tidak menyimpan raw IP, password, token reset, atau session ID dalam audit.
