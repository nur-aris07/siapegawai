# S7.2 — Materi kelas dan progres belajar v1.4

## Perubahan

- Admin Operasional dan Superadmin dapat memperbaiki **materi yang masih draft** (judul, isi teks, urutan) sebelum penerbitan. Materi yang sudah published tidak dapat diedit melalui endpoint S7.2. Audit mencatat bidang yang diubah, bukan isi materi mentah.
- Peserta aktif yang memiliki enrollment aktif dapat menandai materi **published** selesai dengan POST yang dilindungi CSRF dan throttle. Aksi berulang tidak menambah duplikasi karena unique `(user_id, material_id)`.
- Progress dihitung berdasarkan **jumlah materi published saat ini** di kelas terkait. Pada daftar `/kelas-saya` terlihat X dari Y materi dan persentase. Pada detail kelas tampil status per materi dan form `Tandai materi selesai`.
- Progress tetap ada setelah enrollment dicabut (rekam belajar tidak dihapus) tetapi materi tidak bisa dibaca atau ditandai selesai selama akses dicabut. Progres lama kembali terlihat jika Superadmin mengaktifkan enrollment lagi.
- Sidebar yang sebelumnya menuliskan seluruh "Kelas & paket belum aktif" diperjelas menjadi "Paket berbayar — Belum aktif"; kelas dasar sudah tersedia di menu "Kelas & materi".

## Pembatasan

- **Belum** ada pembayaran, file PDF/video, pengingat, penilaian kelas, quiz per materi, sertifikat, atau fitur mengulang/menyetel ulang progres. Penanda selesai adalah aksi manual, bukan bukti peserta sudah membaca dengan sungguh-sungguh.
- Teks materi dirender escaped, tanpa HTML mentah, iframe atau JavaScript dari admin.
- Progres persentase dapat berubah jika admin menerbitkan materi baru, karena penyebutnya adalah jumlah materi terbit yang tersedia saat ini. Materi terbit tidak diedit agar isi historis tetap konsisten.
- Hanya peserta dan admin yang sudah diotorisasi dengan middleware S6 yang dapat mengakses endpoint terkait.

## Pemeriksaan

1. Cadangkan MySQL development, lalu `php siapegawai-update.php` (dry run).
2. Jalankan `php siapegawai-update.php --apply --migrate --verify`.
3. Buat kelas development, tambahkan dua materi teks draft. Edit salah satu draft dan terbitkan keduanya. Buka kelas, berikan akses ke akun Dewa melalui Superadmin.
4. Login Dewa ke `/kelas-saya`, buka kelas dan pilih `Tandai materi selesai`. Pastikan progres menjadi **1 dari 2 materi / 50%**. Klik dua kali atau kirim ulang: hasil tetap satu progress.
5. Uji akun lain dan cabut akses Dewa. Materi tidak dapat dibuka hingga akses diberikan kembali, sementara catatan progress tetap tersimpan.

Jangan gunakan `migrate:fresh` pada database aplikasi. Gunakan DB testing MySQL terpisah untuk PHPUnit.
