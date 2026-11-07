# Siapegawai — keputusan desain dan alur v3

## 1. Sumber keputusan

- Percakapan awal: ide bimbel, latihan soal SKD/SKB, pengajar yang direncanakan (Febilenia dan Ayu), serta website Siapegawai.
- Konsep arsitektur sebelumnya: landing page, tryout CAT, bank soal, kelas, paket, statistik, forum, AI-generated questions.
- `assets.zip`: 4 wordmark SVG/PNG dari logo resmi terbaru; 3 karakter (NARA, BIMA, LARAS), 3 baju/seragam, 9 lembar turnaround, 3 ilustrasi grup.
- Dua panduan audit UI AI: menekankan product specificity, konsistensi desain, konten yang tidak generik, pengalaman error/empty/success, responsivitas, HTML semantik, dan tombol yang benar-benar berfungsi.

> Panduan audit UI AI merupakan heuristik, bukan alat pembuktian asal sebuah desain. Tujuan redesign adalah kualitas produk, bukan sekadar menghindari pola visual tertentu.

## 2. Identitas resmi

**Siapegawai** adalah pengejaan logo terbaru: hanya huruf `S` kapital, sisanya huruf kecil, dan hanya satu huruf `p`.

- Primary logo: `LOGO-001_Primary_Navy`
- Accent logo: `LOGO-002_Navy_and_Gold_SharedP`
- Reversed: `LOGO-003_White_Reversed`
- Monochrome: `LOGO-004_Black_Monochrome` (tersimpan pada paket master asal, tidak dibutuhkan UI ini).

Warna utama: `#0B1B40`; aksen emas dari logo `#B88928`. Aksen emas bukan background setiap card, tetapi penanda konten penting.

## 3. Aset karakter

| Karakter | Atribut | Penempatan v3 |
| --- | --- | --- |
| NARA | hijab, laptop, sisi kiri grup | Hero, story, kelas |
| BIMA | rambut pendek, tablet, sisi tengah | Hero, story, kelas |
| LARAS | rambut panjang, buku, sisi kanan | Hero, story, kelas |

- Grup khaki → hero, melambangkan tujuan menjadi ASN.
- Grup Korpri → bagian cerita brand, menegaskan keunikan aset.
- Grup putih → kelas, variasi konteks visual.
- Lembar turnaround **tidak** dipotong menjadi ilustrasi baru. Ini adalah *master reference* untuk membuat ekspresi/konteks lain nanti tanpa mengubah identitas karakter.

## 4. Sasaran pengguna dan alur utama

Pengunjung awal biasanya ingin memastikan apakah metode latihan cocok **sebelum** harus membuat akun atau membeli paket. Dengan demikian, alur prioritas:

```text
Beranda
   ↓
Coba latihan SKD (tanpa akun)
   ↓
Selesaikan 9 soal / evaluasi topik / pembahasan
   ├── Bank soal → fokus materi yang belum dikuasai
   ├── Progres saya → lihat riwayat dari sesi nyata
   └── Kelas & Paket → pahami opsi pendampingan
                           ↓
                       Daftar minat (demonstrasi)
```

**Navigation MVP:** Beranda · Latihan CAT · Bank soal · Kelas · Paket · Progres saya. Hindari menambah 8–10 item utama sekaligus.

## 5. Pengembangan bertahap

**Tahap 1 (tercakup di v3 frontend):** cerita brand, navigasi publik, tryout contoh, pembahasan, bank soal terbatas, progres lokal, rencana kelas/paket, form validasi non-submitting.

**Tahap 2 (perlu backend):** autentikasi siswa; database soal dan hasil; panel pengajar untuk mengkurasi soal; admin untuk pengaturan kelas dan paket; pendaftaran aktual dengan perlindungan data; dashboard pengguna tersinkronisasi.

**Tahap 3:** kelas langsung, rekaman dan modul, sistem pembayaran, komunitas terkelola, variasi soal berbantuan AI dengan ulasan pengajar, analitik kemampuan pengguna.

**Jangan dulu:** janji meluluskan, testimoni fiktif, passing grade/format BKN tanpa referensi aktual, angka statistik pengguna, leaderboard nasional tanpa data valid, klaim 'AI-generated endless questions' tanpa verifikasi.

## 6. Kriteria UI

- Fokus pada konten yang benar-benar dipakai: soal dan pembahasan mendapat visual utama, sedangkan card dekoratif dikurangi.
- Menggunakan design tokens: spacing teratur, border ringan, dua tingkat radius praktis, warna navy/emas, font sans yang dapat dibaca.
- Ikon outline pada grid 24×24 dengan stroke seragam, mengambil pendekatan visual yang sejalan dengan Tabler Icons (MIT); diembed agar prototipe bisa dibuka offline tanpa dependency webfont.
- Label dan CTA spesifik: *Coba latihan SKD*, *Buka bank soal*, *Catat minat kelas*.
- Empty state nyata: belum ada riwayat latihan; tidak ada soal cocok.
- Error state form: nama kurang dari 2 karakter, email tidak valid.
- Konfirmasi saat keluar dari sesi berjalan, mengumpulkan jawaban belum lengkap, dan menghapus riwayat.
- Responsif melalui breakpoint 1100 / 830 / 600 / 370 piksel, dukungan keyboard, outline fokus, skip navigation, dan reduced motion.

## 7. Status kebenaran konten

Soal hanya contoh ilustratif. Untuk produk komersial, tutor perlu mengulas materi, jawaban, penjelasan, waktu dan metode penilaian. Skor sekarang adalah hitungan jawaban sesuai dari 9 (bukan skor CAT/ambang kelulusan resmi). Kredensial tutor, jadwal dan harga belum dikonfirmasi; jangan membuat data rekaan.
