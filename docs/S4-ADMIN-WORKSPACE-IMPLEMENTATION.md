# Siapegawai — Admin Workspace v1 dan Fondasi Bank Soal S4

**Versi patch:** 0.3 — 8 November 2026  
**Basis:** UI Recovery v0.1 + Navigation Hotfix v0.2 (Laravel 13, PHP 8.3+, Yajra 13, MySQL/MariaDB).  
**Status:** *Kode telah disusun, linter & installer diuji secara lokal. PHPUnit/Laravel runtime dan visual browser di project Windows tetap harus diverifikasi.*

## Rangkuman

- Workspace admin terpisah dari publik dan akun, menggunakan referensi struktur INA-GOV **tanpa menyalin identitas**. Palet, font, ilustrasi, dan design direction Siapegawai v4 tetap dipakai.
- Header, menu aktif, sidebar responsif; tidak membuat tautan menu untuk fitur yang belum berjalan. Metrik database, bukan angka dekoratif.
- S4 fondasi: skema `subjects`, `topics`, `questions`, `question_versions`, `question_options` dengan FK, indeks dan versioning. Kode, soal, opsi, pembahasan, draft, publish dan archive. Audit setiap perubahan penting.
- `/admin/soal` menampilkan Blade pada GET biasa dan JSON Yajra pada GET AJAX **route yang sama**. Akses hanya admin operasional/superadmin aktif melalui middleware **dan** policy. JS tidak menyisipkan HTML dari database.
- Soal terbit tidak dimutasi. Simpan edit membuat draft versi baru; publish mengubah pointer `current_version_id`. Soal demo 12 item tetap JSON terpisah.
- Seed kategori awal: TWK, TIU, TKP, SKB, dan topik `Umum`; **tidak** memasukkan 12 soal demo ke produksi. Verifikasi materi/kunci tetap wajib oleh Admin sebelum terbit.

## Cara pakai — installer satu file

Simpan `siapegawai-update-v0.3.php` di root Laravel, jalankan:

```powershell
php siapegawai-update-v0.3.php
php siapegawai-update-v0.3.php --apply --migrate --verify
```

**Sebelum `--migrate`, buat backup database development jika sudah ada data penting.** `--apply` saja memasang file tanpa memodifikasi DB; `--migrate` secara eksplisit menjalankan migration dan seed kategori secara idempotent. Pada `APP_ENV=production`, instalasi migrasi otomatis ditolak. Pengujian hanya boleh jalan bila `phpunit.xml` menunjuk DB terpisah.

Setelah lulus: buka `/admin`, `/admin/registrasi`, `/admin/soal`, lalu tambah satu soal draft. Periksa proses versi dan publishing. Laporkan `install-report.txt` dan error dari `command-output.txt` (hapus rahasia jika ada) jika gagal.

## Pengujian minimum

1. Guest harus diarahkan ke login ketika membuka `/admin/soal`; peserta aktif tetap ditolak (403). Admin aktif dapat membuka tabel maupun JSON Yajra.
2. Form valid dapat membuat soal draft; data tidak otomatis terbit dan **tidak muncul** di `/bank-soal` publik.
3. Publish membutuhkan review, kunci, opsi, dan pembahasan. Status berubah ke published, hash versi dan audit disimpan.
4. Setelah published, simpan edit membentuk versi `draft` baru, sedangkan versi yang sudah published tetap utuh.
5. Arsip membutuhkan alasan, tidak menghapus versi, tidak dapat diedit lagi.
6. Form dan DataTables escape konten; teks `<script>` tidak menjadi HTML; tidak ada inline script/style baru.
7. Layout dilihat di 390, 768, 1024, 1440; fokus keyboard, scrolling tabel, sidebar mobile, loading dan error state.

## Batas yang belum dibuat

- CRUD master kategori/topik (saat ini menggunakan seeder aman; editing master jadi backlog), import soal hasil review, rubric bobot TKP final, dukungan rich text/gambar soal, operasi bulk/CSV.
- Engine tryout anggota, exam attempts, server timer, scoring, paket/kelas, dan manajemen superadmin; ini pekerjaan S5/S6 berikutnya.
- CSP enforce, MFA dan audit keamanan komprehensif sebelum produksi, seperti tertera di Implementation Plan v2.2.
- Browser visual regression belum dijalankan di mesin Windows; **jangan menyebut desain pixel-perfect/production ready hanya dari PHP test.**

## Keputusan UI

Layout mengambil prinsip sidebar, header profil, toolbar dan empty state dari contoh INA-GOV yang disediakan pengguna. Identitas Siapegawai tetap Plus Jakarta Sans, navy/gold, kontrol radius kecil, border tipis, shadow seperlunya. Halaman login/publik tidak diubah. Tidak ada mock statistic, menu link palsu, animasi dekoratif, atau dependensi UI baru.
