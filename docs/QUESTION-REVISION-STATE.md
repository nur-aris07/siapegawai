# Siapegawai — Publikasi soal vs revisi draft (hotfix v0.4)

Status `questions.status=published` menunjukkan **ada versi soal yang saat ini aktif**, bukan berarti semua perubahan terbaru sudah diterbitkan.

- Soal baru: `questions.status=draft`, versi terbaru `question_versions.review_status=draft`, `current_version_id=null`.
- Setelah terbit v1: `questions.status=published`, `current_version_id=v1`, versi terbaru published.
- Setelah mengedit dan menyimpan: `questions.status=published`, `current_version_id` tetap menunjuk v1; versi terbaru (v2) draft.
- Setelah menerbitkan v2: `current_version_id=v2`, versi terbaru published.
- Jika isi v2 dikembalikan sama dengan v1, v2 **tetap draft** sampai diterbitkan atau ada mekanisme pembatalan draft. Perilaku ini bukan pembatalan otomatis.

## Yang diubah v0.4

Editor dan daftar soal menampilkan dua status secara terpisah: **Terbit v1** dan **Revisi draft v2**. Tombol di editor secara eksplisit bertuliskan **Terbitkan revisi v2**. Kontrak database, mekanisme publish, dan hasil tryout tidak berubah.

## Belum dalam patch ini

- Tombol **Batalkan draft revisi** dengan audit; perlu keputusan lifecycle agar tidak menghapus riwayat audit secara tidak sengaja.
- Deteksi revisi yang secara konten identik dengan versi aktif, untuk mencegah publikasi duplikat.

Tidak diperlukan migration. Jalankan `npm run build` **sebelum** PHPUnit karena perubahan JavaScript/CSS memakai Vite.
