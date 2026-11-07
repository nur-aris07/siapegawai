# Siapegawai — prototipe v4 (iterasi Quality Control)

Redesign berbasis dokumen `Siapegawai_UI_Design_Direction_dan_Quality_Control.md` tanggal 7 November 2026. Tidak ada klaim bahwa prototipe ini adalah layanan resmi BKN.

## Jalankan

Buka `index.html` lewat browser modern. Struktur tidak membutuhkan proses build:

- `index.html` — layout semantik, navigasi, dialog privasi, dan SVG icons outline.
- `css/styles.css` — komponen, layout, warna, responsive.
- `js/app.js` — data soal demo, interaksi bank soal, tryout, nilai, riwayat browser.
- `assets/*` — wordmark resmi Siapegawai dan ilustrasi Nara, Bima, Laras dari aset yang disediakan.

Opsional: jalankan server lokal dengan `python -m http.server 8000` di direktori ini, lalu buka `http://localhost:8000`. Font Plus Jakarta Sans mengambil dari Google Fonts saat tersedia; jika tidak, fallback ke font sistem.

## Halaman

1. **Beranda** — penjelasan produk, CTA mencoba latihan, preview soal asli, karakter ASN.
2. **Latihan SKD** — 9 soal contoh (3 TWK, 3 TIU, 3 TKP) dan timer 12 menit; input radio, navigasi, penanda ragu, submit + konfirmasi, hasil dan pembahasan.
3. **Bank soal** — 12 contoh soal dengan pencarian, filter kategori, jumlah hasil dinamis, reset hasil kosong, pembahasan disclosure.
4. **Kelas** — draft silabus saja; kelas belum dibuka dan pengajar belum dikonfirmasi untuk publik.
5. **Paket** — hanya paket contoh gratis yang tersedia. Konsep paket berbayar dinyatakan belum tersedia, tanpa tombol pembelian/pendaftaran.
6. **Progres saya** — riwayat latihan lokal, detail pembahasan untuk sesi baru, konfirmasi hapus. Riwayat lama v3 hanya menyimpan nilai ringkas dan tidak dapat membuka pembahasan.

## Batasan dan kejujuran klaim

- Soal, jawaban, dan pembahasan bersifat **konten awal untuk demonstrasi produk**; perlu validasi oleh tutor untuk penggunaan komersial.
- Semua kategori, skor, timer, dan nilai **bukan** simulasi resmi CAT BKN, passing grade BKN, atau prediksi kelulusan. Dalam prototipe, satu jawaban cocok dengan kunci contoh = satu poin, termasuk TKP.
- Riwayat disimpan dengan `localStorage` pada browser tempat sesi dijalankan jika penyimpanan tersedia. Tidak ada akun, server, sinkronisasi, backup, atau layanan pengumpulan data pribadi.
- Tidak ada pendaftaran minat, transaksi, pembayaran, sistem kelas langsung, generator AI, atau leaderboard.
- Logo, atribut karakter, ejaan `Siapegawai`, dan palet mengikuti aset pengguna. Ikon adalah SVG outline lokal (selaras secara visual dengan pendekatan Tabler), bukan pemanggilan library eksternal Tabler.

## QC

Lihat `QC-v4.md` untuk daftar perubahan, bukti pengujian, dan hal-hal yang belum terverifikasi. Pada iterasi ini pengujian otomatis dilakukan di Chromium melalui injeksi halaman dengan aset inline dan polyfill localStorage karena navigasi origin lokal diblokir lingkungan pengujian. Maka pengujian tersebut **tidak membuktikan** kemampuan browser biasa melakukan reload lintas sesi, kompatibilitas penuh screen reader, atau performa nyata hosting.
