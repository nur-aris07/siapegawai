# Siapegawai S5.1 — Riwayat & Progres Peserta (v0.8)

## Cakupan

- Route khusus peserta aktif: `GET /peserta/tryout/riwayat` (`member.tryouts.history`).
- Daftar hasil terurut terbaru, 10 hasil per halaman, termasuk hasil template yang sudah diarsipkan.
- Ringkasan hasil dari server: jumlah percobaan selesai, skor terbaik, rata-rata, total jawaban benar.
- Sesi masih aktif ditampilkan terpisah; sesi kedaluwarsa diproses dengan metode finalisasi S5 yang sudah teruji (batas 50 sesi kedaluwarsa per permintaan).
- Link menuju hasil/pembahasan yang sudah ada, tanpa membuka akses lintas akun.
- Navigasi pada Workspace Peserta dan halaman daftar tryout.
- Tetap menggunakan layout dan font Siapegawai; CSS lokal, tanpa JS inline, CDN, dependency baru, atau migrasi DB.

## Batasan

- Skor **internal**, 1 poin untuk setiap soal benar; bukan bobot CAT SKD/TKP resmi.
- Belum ada grafik, rekomendasi otomatis materi, sertifikat, atau analitik kategori.
- Tidak mengubah CAT, timer, autosave, versioning soal, atau template admin.
- S5.1 ini tidak menyediakan akun peserta otomatis; pengujian browser memerlukan akun peserta aktif yang sah di lingkungan development.

## Uji di browser

1. Login sebagai peserta aktif, buka `/peserta/tryout/riwayat` sebelum ada hasil: tampil empty state.
2. Selesaikan template tryout pada `/peserta/tryout`, kembali ke Riwayat: hasil terlihat.
3. Mulai percobaan lain jika diizinkan: sesi aktif terlihat dan bisa dilanjutkan.
4. Logout atau gunakan akun peserta lain: riwayat tidak terbuka; hasil orang lain tidak dapat diakses.
5. Gunakan template yang diarsipkan: hasil lama tetap muncul.

## Pengujian otomatis

`php artisan test --filter=SiapegawaiMemberHistoryTest`

Untuk seluruh suite: `php artisan test`, menggunakan database MySQL testing yang **berbeda** dari database development. Build Vite dijalankan sebelum PHPUnit.
