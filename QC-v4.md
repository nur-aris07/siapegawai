# QC Siapegawai v4 — tindak lanjut evaluasi 7 November 2026

## Keputusan produk

Pertahankan identitas visual yang sudah kuat; fokus pada masalah P0 dan P1. Pisahkan gaya editorial Beranda dari antarmuka belajar yang lebih ringkas, eksplisit, dan fokus pada tugas. Warna navy-krem-gold dan karakter Nara, Bima, Laras tetap dipakai selektif. Tidak mengklaim afiliasi BKN, keakuratan penilaian resmi, data peserta, kelas tersedia, atau paket berbayar.

## Perubahan terimplementasi

| ID dokumen | Status iterasi | Bukti / keputusan |
| --- | --- | --- |
| UI-01 | **Diperbaiki** | `Bank Soal` tidak lagi menggunakan string literal `${Q.length}`; jumlah 12 contoh terhitung dari data. Filter, pencarian, dan hasil kosong dinamis. |
| UI-02 | **Diperbaiki** | Menghapus formulir minat demo, label daftar minat, dan CTA pembelian semu. Kelas/paket yang belum siap hanya berupa informasi, bukan proses pendaftaran. |
| UI-03 | **Diperjelas, belum sepenuhnya teruji di origin nyata** | Pemeriksaan tersedianya localStorage; kegagalan simpan diberi pesan. Riwayat baru menyimpan detail jawaban untuk dibuka lagi. Tidak mengklaim data di server. |
| UX-01 | **Diperbaiki** | Laman awal latihan dipersingkat; 9 soal, timer 12 menit, materi, rubrik demo, dan tombol Mulai tampil di layar awal desktop 1366×768. |
| UX-02 | **Diperbaiki, QC aksesibilitas menyeluruh tertunda** | Input radio native, navigasi 9 nomor, tanda tinjau, dialog konfirmasi submit, penilaian, dan ulasan per soal. Alur otomasi telah dijalankan. |
| UX-03 | **Diperbaiki** | Pencarian dan filter memiliki count dan empty state; disclosure jawaban asli; reset bekerja. |
| UX-04 | **Diperbaiki** | Preview Beranda memakai data soal TWK pertama yang sama persis dengan data latihan, memuat empat opsi dan kunci dalam disclosure. |
| UX-05 | **Diperbaiki** | Kelas menunjukkan status perencanaan dan silabus di awal. Paket gratis dipisahkan dari opsi berbayar yang masih konsep. |
| VI-01..VI-06 | **Sebagian** | Halaman tugas mendapat heading ringkas dan susunan kontekstual, font family dipangkas menjadi satu, label gold digelapkan, ilustrasi sekunder diperkecil; audit kontras semua state belum dilakukan. |
| QC-01 | **Sebagian teruji** | Tidak ada overflow horizontal di enam route pada 320, 375, 768, 1024, 1366, 1440 px menggunakan Chromium dalam mode uji inline. Perlu tes manual real-device. |
| QC-02 | **Belum tuntas** | Focus-visible, radio native, semantic headings, label, dan Escape pada menu sudah tersedia. Belum menjalankan audit screen reader, zoom 200%, atau pemeriksaan WCAG menyeluruh. |

## Pengujian otomatis yang dijalankan

Pengujian melalui Chromium+Playwright dengan CSS/JS/aset di-inject pada halaman lokal sementara karena kebijakan browser lingkungan membatasi navigasi ke URL lokal. Penyimpanan diuji menggunakan polyfill `localStorage`; hal ini **bukan** tes persistence yang valid antar-reload/browser-origin nyata.

**Hasil: 20 skenario PASS, 0 FAIL** untuk tes yang dijalankan: Beranda, 12 soal Bank, TIU=3, no-results/reset, disclosure pembahasan, status kelas/paket, CTA latihan terlihat, radio soal, jawaban setelah navigasi, tandai soal, perhitungan skor dan 9 pembahasan, penyimpanan di polyfill dan re-render, buka riwayat, hapus riwayat, tidak ada form palsu, menu mobile buka/tutup dengan Escape. Tidak ada overflow terdeteksi pada enam route di enam lebar yang diuji.

## Belum dianggap DONE / rilis produksi

- **P0/P1 yang membutuhkan uji nyata:** retest storage dengan reload dan private mode, hasil timer habis, reset browser, serta review jawaban dengan keyboard pada hosting staging.
- Audit warna dengan perhitungan kontras, zoom 200%, screen reader, tata urut fokus, validitas materi oleh tutor, serta kinerja lab dan Core Web Vitals di hosting sungguhan.
- Tidak ada proses pembayaran, formulir aktif, akun, database, kurasi soal, atau jadwal kelas; konten tersebut tetap rencana.
- Belum dilakukan user testing dengan calon peserta; nilai keberhasilan tugas belum dapat diklaim.

**Keputusan QC:** layak untuk **review prototipe berikutnya**, **belum** menjadi release candidate produksi. Lakukan uji langsung pada staging dan peninjauan konten sebelum publikasi.
