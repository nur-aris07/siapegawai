# Siapegawai S6.1 — Direktori & Pengamanan Akun Peserta (v1.1)

## Batas perubahan

- **GET /admin/peserta**: Blade dan JSON Yajra pada URL dan controller method `index()` yang sama. Hanya `role=participant`; filter nama/email dan status.
- **GET /admin/peserta/{user}**: detail identitas, ringkasan percobaan CAT, status registrasi/grant, audit pengamanan akun.
- **POST /admin/peserta/{user}/status**: hanya Superadmin aktif, membutuhkan konfirmasi dan alasan minimal 12 karakter; transaksi database dan audit.
- Tidak membuat, menghapus, atau menaikkan role pengguna. Tidak mengubah password, hasil CAT, grant, status registrasi, atau keputusan aktivasi WhatsApp.
- Akun `pending`/`rejected` tidak bisa diaktifkan melalui menu ini. Hanya akun yang ditangguhkan secara resmi bisa dipulihkan; proses registrasi awal tetap pada menu Registrasi.
- Penangguhan memblokir akses CAT dan dashboard melalui middleware `EnsureActiveAccount` pada request berikutnya. Jika peserta sedang mengerjakan CAT, timer server tidak berhenti ketika ditangguhkan.

## Keamanan dan batasan

- Gunakan HTTPS + MFA Admin sebelum produksi; fitur ini **bukan** implementasi MFA atau manajemen seluruh sesi login.
- Akses admin operasional sebatas baca; Superadmin berwenang suspend/restore; otorisasi dicek di controller dan service, bukan berdasarkan tombol UI.
- Semua teks dari MySQL ditampilkan melalui Blade escaped atau `textContent` JS. Tidak menggunakan JS/CSS inline. Form perubahan memakai POST + CSRF dan throttling.
- Riwayat Audit tidak dapat diubah dari UI. Tindakan tercatat alasan serta status sebelum/sesudah, tanpa menyimpan kata sandi.
- Suspend tidak otomatis menghapus sesi PHP yang sudah ada. Middleware mengecek `status` pada request ke route privat. Revokasi lintas perangkat dan MFA tetap pekerjaan keamanan berikutnya.
- Account maintenance bukan pengganti proses legal/verifikasi pendaftaran.

## Acceptance

1. Dry run `CONFLICT=0`, lalu `--apply --verify` lulus.
2. Guest/peserta tidak bisa membaca direktori, termasuk AJAX Yajra.
3. Operational Admin melihat daftar/detail tetapi tak bisa suspend.
4. Superadmin menangguhkan peserta aktif dengan alasan/konfirmasi: audit muncul, akses CAT peserta 403, jawaban dan nilai lama tidak berubah.
5. Superadmin memulihkan peserta yang benar-benar ditangguhkan: akses CAT kembali; tidak ada grant baru dan status registrasi tetap.
6. Akun pending/rejected, Superadmin, dan Operational Admin tidak dapat diaktifkan/dinonaktifkan lewat endpoint ini.
7. Input XSS ditampilkan sebagai teks, bukan HTML yang dieksekusi.

## Deploy dan rollback

- Tidak ada migration. Pemasangan membuat backup file lama, dan perintah verifikasi membangun Vite **sebelum** PHPUnit.
- Jika kode perlu di-rollback, pulihkan file dari `.siapegawai-backup` terkait dan review route/cache; **jangan** hapus data atau reset database.
- Penangguhan/pemulihan akun yang sudah diproses **tidak boleh otomatis di-rollback** hanya dengan mengganti source. Itu adalah perubahan data yang tercatat di audit; harus ditinjau secara terpisah.
