# Siapegawai v4 — design notes

## North star

**Editorial untuk memperkenalkan brand; fungsional untuk mengerjakan tugas belajar.**

Beranda memperkenalkan Siapegawai melalui tipografi, grup Nara/Bima/Laras, demonstrasi soal, dan CTA utama. Latihan, Bank Soal, Kelas, Paket, dan Progres mengikuti struktur per tugas (bukan setiap halaman sebagai landing page). Copy menggambarkan jumlah dan cakupan nyata, bukan angka social-proof rekaan.

## Hierarki pengalaman

Beranda → Mulai latihan SKD → Jawab 9 soal (TWK/TIU/TKP) → Tinjau sebelum mengumpulkan → Hasil dan pembahasan → Perkuat materi dari Bank Soal → Progres di browser yang sama.

Untuk pengunjung yang belum mau latihan: Beranda → Bank Soal → Filter dan cari → Buka pembahasan. Kelas/paket tidak menawarkan pendaftaran atau pembayaran sampai proses itu benar-benar aktif.

## Identitas

- Wordmark asli `Siapegawai`, tidak diganti icon/logo generatif.
- Palet navy, krem, dan gold; gold gelap untuk label kecil dan gold terang hanya sebagai aksen.
- Plus Jakarta Sans dengan fallback sistem; SVG icon outline lokal seragam, pendekatan mirip Tabler.
- Tiga karakter: Nara, Bima, Laras, tanpa menghasilkan gambar tambahan di luar aset.
- Kontrol utilitas memiliki radius kecil, border dan spacing fungsional, minim shadow.

## Batasan

Ini adalah prototipe edukasi independen dan bukan layanan BKN. Paket premium, kelas, pengajar, harga, pendaftaran, data server, login, serta pembayaran belum tersedia. Bank Soal hanyalah 12 contoh materi dan perlu kurasi tutor. Catatan hasil hanya disimpan lokal bila browser mendukungnya. Evaluasi skor adalah kesesuaian 1 poin per kunci latihan, bukan rubrik BKN.

Lihat `QC-v4.md` untuk status perbaikan, bukti, dan item QC yang belum diverifikasi.
