# Siapegawai v0.7 — S5 CAT peserta (baseline)

## Batas implementasi

- Akses untuk akun aktif `participant`; admin, guest, pending ditolak middleware.
- `GET /peserta/tryout`: daftar template `published`, riwayat dan batas percobaan.
- Start/resume: transaksi dan lock baris user agar dua tab tidak bisa melewati batas percobaan.
- `GET /peserta/tryout/sesi/{attempt}`: memuat versi soal dari `tryout_package_items.question_version_id`, tanpa kunci jawaban.
- `PATCH .../jawaban`: autosave per nomor di MySQL, validasi pilihan dan kepemilikan sesi, jawaban tidak bocor ke peserta lain.
- Timer: `expires_at` dari database; semua perubahan setelah batas waktu ditolak. Menutup browser **tidak** menghentikan waktu.
- Kumpulkan / waktu habis: scoring di server, satu poin per jawaban yang cocok. Hasil, kunci, pembahasan hanya muncul setelah selesai.
- Histori versi soal template dibekukan sejak terbit; versi terbaru master soal tidak mengganti materi tryout lama.
- Browser UI mengikuti pola CAT editorial v4 tanpa menambahkan hero generik, tanpa inline JS/CSS.

## Yang belum termasuk

Tidak ada sistem pembayaran, ambang batas kelulusan resmi, skoring berbobot TKP, penjadwalan batch, proctoring, shuffle soal, peninjauan kecurangan, atau sertifikat. Skor adalah hasil latihan internal dan **bukan simulasi resmi BKN**. Tidak ada notifikasi push.

## Keamanan dan verifikasi

- Wajib `csrf-token` pada layout peserta untuk PATCH; hanya domain sendiri.
- Validasi akses peserta aktif, kepemilikan attempt, kepemilikan item ke paket, dan opsi ke versi soal.
- Gunakan endpoint POST/PATCH untuk mengubah jawaban/status.
- Migration `2026_11_08_000006_create_member_tryout_attempts.php` harus dibackup DB development sebelum `--migrate`.
- `npm run build` dilakukan **sebelum** PHPUnit karena view peserta memakai entry Vite baru.
- Pastikan `.env APP_ENV` bukan production saat memakai installer dengan `--migrate`.
- `phpunit.xml`: `DB_DATABASE=siapegawai_testing`, tidak boleh menggunakan DB aplikasi.
- Smoke test satu template `published` dengan minimal satu pertanyaan terbit; buat peserta aktif (berbeda dari superadmin), lalu jalankan start, autosave, refresh browser, submit, result. Uji juga expiration dan percobaan lebih dari batas.
