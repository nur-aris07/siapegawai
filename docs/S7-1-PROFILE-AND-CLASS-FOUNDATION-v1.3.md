# S7.1 — Profil peserta dan fondasi kelas v1.3

## Scope

- `GET /profil`, `PATCH /profil`, `POST /profil/password` untuk peserta aktif. Hanya nama dan nomor WhatsApp yang boleh diperbarui. Email, role, status, dan hak akses tidak dapat diedit sendiri. Password baru membutuhkan password saat ini; sesi browser ini diakhiri setelah perubahan.
- Kelas baru dibuat `draft`, dengan deskripsi/silabus dan kapasitas opsional. Materi teks disimpan sebagai `draft` hingga ditinjau dan diterbitkan.
- Kelas hanya dapat dibuka (`open`) jika memiliki minimal satu materi terbit. Enrollment diberikan **manual oleh Superadmin** untuk peserta berstatus active. Pemberian dan pencabutan akses memerlukan alasan, konfirmasi, dan audit.
- `/kelas-saya` serta `/kelas-saya/{class}` hanya milik peserta aktif dengan enrollment aktif. Hanya materi `published` yang bisa dibaca.
- Tidak ada upload atau download dokumen, pembayaran, checkout, kelas video, jadwal, akses otomatis, atau perubahan katalog publik `/kelas` pada patch ini. Materi teks selalu ditampilkan escaped, tanpa HTML mentah.
- Login email dan password tetap tanpa OTP/MFA.

## Cara uji

1. Backup MySQL `siapegawai`.
2. `php siapegawai-update.php` untuk dry run.
3. `php siapegawai-update.php --apply --migrate --verify`.
4. Login peserta aktif, buka `/profil`, ubah nama, coba ganti password lalu login ulang.
5. Login Superadmin, buka `/admin/kelas`, buat kelas draft. Tambah materi, terbitkan materi, lalu ubah status kelas menjadi Dibuka.
6. Gunakan ID peserta aktif yang tersedia pada `/admin/peserta`; berikan akses dengan alasan. Login peserta di browser lain, buka `/kelas-saya` dan baca materi.
7. Cabut akses, pastikan halaman materi kembali `404` tanpa menghapus data kelas atau riwayat audit.

## Batas keamanan

- Jangan menggunakan `migrate:fresh` atau database produksi untuk pengujian.
- Pencabutan enrollment diperiksa pada **setiap request kelas**, termasuk sesi yang sudah login.
- Penggantian password peserta mengakhiri **sesi sekarang**; pencabutan semua sesi peserta di perangkat lain belum diimplementasikan (tidak boleh diasumsikan selesai).
- `enrollments` bukan verifikasi pembayaran; semua akses masih diberikan secara manual.
- Satu batch pertama adalah proof-of-flow; materi dan kelas nyata memerlukan kurasi, kebijakan privasi dan prosedur layanan sebelum go-live.
