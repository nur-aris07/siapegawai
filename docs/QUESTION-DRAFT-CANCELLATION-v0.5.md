# Siapegawai v0.5 — pembatalan draft revisi

## Batas perilaku

- Soal terbit v1 masih aktif ketika draft revisi v2 dibuat. Penyimpanan ulang draft menggunakan v2 yang sama.
- Menyimpan ulang konten yang sama persis tidak membuat draft atau audit perubahan baru. Komparasi meliputi pertanyaan, pembahasan, opsi dan kunci, serta kategori bila belum terbit.
- Draft revisi dengan isi sama seperti versi terbit ditolak ketika dipublikasikan untuk mencegah publikasi duplikat.
- Pembatalan hanya untuk **draft revisi terbaru pada soal yang telah terbit**, tidak untuk draft awal v1 atau versi yang sudah diterbitkan.
- POST /admin/soal/{question}/discard-draft memakai auth, middleware role, policy, CSRF, throttle, konfirmasi, alasan minimal 10 karakter, transaksi dan row locking.
- Draft yang belum terbit beserta opsinya dihapus. Audit append-only mencatat aktor, waktu, alasan, ID draft yang dibuang, versi terbit aktif dan status. Isi konten tidak disimpan ulang di audit.
- Nomor versi draft yang dibatalkan bisa dipakai lagi. Versi terbit yang sudah berlaku tidak pernah dihapus atau diubah.
- Apabila draft telah direferensikan oleh fitur lain, foreign key harus mencegah penghapusan dan transaksi rollback. Sebelum mengaktifkan S5, pastikan hanya versi published yang dapat dipakai tryout.

## Pengujian manual

1. Buat draft v1 → terbitkan. Simpan tanpa perubahan, pastikan tetap hanya v1.
2. Edit kunci C → A, simpan berulang, pastikan hanya draft v2.
3. Ubah lagi ke C lalu terbitkan: ditolak karena sama dengan v1.
4. Buka Batalkan draft, isi alasan, centang konfirmasi. Pastikan status kembali Terbit v1 tanpa draft.
5. Verifikasi `audit_logs` berisi `question.draft_discard`; peserta tidak boleh memanggil endpoint.

Tidak mengubah migration, user, data soal yang sudah ada, atau halaman publik.
