# Siapegawai — v3 editorial/product-first prototype

## Menjalankan

Buka `index.html` menggunakan browser modern. Tidak perlu npm, build, akun, atau server. Semua asset gambar dan ikon outline yang dipakai dibundel bersama file website; font online (Plus Jakarta Sans dan DM Sans) memiliki fallback sistem.

Untuk pengujian yang menyamai hosting, jalankan `python -m http.server 8000` dari folder ini, lalu buka `http://localhost:8000`.

## Apa yang disertakan

- Beranda editorial dengan wordmark resmi **Siapegawai** (huruf S kapital, satu huruf p) dan karakter master NARA/BIMA/LARAS dari aset pengguna.
- Jalur pengguna: beranda → latihan singkat → hasil/pembahasan → bank soal → rencana kelas/paket.
- Latihan SKD interaktif 9 soal / 12 menit, navigasi, status terjawab dan ragu, konfirmasi sebelum submit, auto-submit setelah waktu habis.
- Bank soal contoh 12 soal dan filter TWK/TIU/TKP/SKB, pencarian, pembahasan.
- Progres riil dari pengerjaan latihan dalam localStorage browser; dilengkapi kondisi kosong dan hapus riwayat.
- Rancangan kelas dan paket tanpa harga, jadwal, klaim institusi, testimoni, atau statistik fiktif.
- Form minat yang divalidasi, dengan pernyataan jelas bahwa data tidak dikirim ke server.
- Aksesibilitas dasar: heading semantik, link skip to content, focus visible, label form, nav mobile, reduced motion.

## Panduan desain & brand

1. Gunakan **Siapegawai** dari dokumen logo terbaru (bukan penulisan lama `SiApegawai` pada README karakter).
2. Gunakan warna logo aslinya: navy `#0B1B40` dan emas `#B88928`, bukan semua elemen diberi gradient.
3. Karakter master: NARA (kiri, laptop), BIMA (tengah, tablet), LARAS (kanan, buku); dalam variasi putih, Korpri, dan khaki. Situs memuat salinan group art .webp yang diperkecil untuk web, tidak memodifikasi desain karakter.
4. Ikon inline mengikuti konvensi outline Tabler pada grid 24×24 dengan stroke konsisten dan penggunaan secukupnya; tidak perlu memuat icon webfont ribuan glyph untuk beberapa ikon.
5. Tidak menggunakan testimoni, angka pengguna, peringkat nasional, atau passing grade yang tidak didukung data. Soal hanya contoh demo.
6. Pembahasan materi dan bobot penilaian harus melalui kurasi pengajar sebelum dipakai sebagai produk komersial.

## Produksi lanjutan

- Backend akun, database, CMS soal, otorisasi, dan peran tutor/admin.
- Pemeriksaan kurikulum dan kurasi konten oleh tutor; penyesuaian ketentuan resmi BKN apabila ingin membuat simulasi formal.
- Pembayaran dan paket hanya setelah alur legal/operasional siap.
- Pengiriman form minat berizin ke API dan penambahan kebijakan privasi riil.
- Aksesibilitas audit, uji E2E, keamanan dan logging server.
- Forum diskusi, live class, generator soal AI dengan proses human review dapat menjadi fase berikutnya.

Aset master awal (`assets.zip`) milik pengguna tidak ditimpa. Situs ini hanya memakai beberapa varian logo SVG dan ilustrasi grup WebP yang sudah disiapkan untuk pengiriman lebih ringan.
