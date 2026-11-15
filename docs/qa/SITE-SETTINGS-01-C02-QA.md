# SITE-SETTINGS-01 — Fase C / C-02 — UX correction & QA

**Status:** C-02 candidate untuk owner UAT; **belum OWNER-ACCEPTED, belum Git published**.
**Baseline:** `main` HEAD `75afa10c94adfc34dfa0134860e781d15a0605c5` + seluruh 10 file C-01 terpasang sesuai hash C-01. Installer C-02 harus menolak konflik dan staged changes.

## Perubahan UX (tanpa ubah logic penyimpanan)

- Sidebar Pengaturan Website menggunakan simbol `ic-settings` (gear) yang disediakan di sprite proyek.
- `/admin/website` kini menampilkan tabel delapan key whitelist; row **Edit** untuk nilai terisi dan **Tambah** untuk key opsional yang kosong. Tombol toolbar **Tambah** hanya menawarkan key opsional kosong. Tidak ada key arbitrer.
- **Hapus** adalah tombol HTML disabled di setiap row, tanpa handler atau DELETE endpoint.
- Editor satu field terpilih lewat `GET /admin/website?setting={known-key}`. Form mengirim semua field whitelisted, yang selain field aktif dikirim sebagai hidden input. Controller tetap memvalidasi semua field, token versi, URL, konfirmasi, dan role seperti C-01. Tidak membuat draft permanen.
- Preview hanya merinci nilai yang berubah, menampilkan simulasi singkat, dan tetap membutuhkan checkbox konfirmasi sebelum `PUT /admin/website`.
- Styling terbatas pada class `site-settings-*` di stylesheet Admin; tidak mengubah shell publik/peserta.
- Tabel `site_settings` existing tetap dipakai. **Tidak** ada migrasi, SQL, Composer, perubahan `.env`, route baru, atau modifikasi fitur Paket/CAT/QQ/Kelas/Legal/Google/SMTP.

## Pengujian operator Windows (hanya setelah pemeriksaan HEAD/remote dan dry-run)

Dari root proyek dengan PHP 8.3 yang digunakan owner:

```bat
"E:\Development\Runtime\PHP\8.3\php.exe" siapegawai-update.php --dry-run && "E:\Development\Runtime\PHP\8.3\php.exe" siapegawai-update.php --apply && "E:\Development\Runtime\PHP\8.3\php.exe" siapegawai-update.php --verify && "E:\Development\Runtime\PHP\8.3\php.exe" artisan optimize:clear && "E:\Development\Runtime\PHP\8.3\php.exe" artisan view:cache && "E:\Development\Runtime\PHP\8.3\php.exe" artisan view:clear && "E:\Development\Runtime\PHP\8.3\php.exe" artisan test tests\Feature\SiapegawaiSiteSettingsTest.php && "E:\Development\Runtime\PHP\8.3\php.exe" artisan test tests\Feature\SiapegawaiAdminInvitationTest.php && "E:\Development\Runtime\PHP\8.3\php.exe" artisan test && npm run build && git diff --check && git status --short --branch
```

Jalankan PHPUnit hanya pada koneksi pengujian `siapegawai_testing`, **bukan DB aktif**. Jangan jalankan `migrate:fresh` pada DB aktif.

## Checklist UAT owner

1. Superadmin masuk `/admin/website`; cek ikon gear dan 8 baris tabel, juga pada mobile.
2. Klik **Tambah** untuk kontak/sosial kosong; klik **Edit** untuk nama/tagline/setting terisi. Tidak ada pilihan membuat key arbitrer. `?setting=mail_password` tidak membuka editor.
3. Semua tombol **Hapus** benar-benar nonaktif; request DELETE ditolak.
4. Preview **tidak** memperbarui konten publik/audit; form preview menunjukkan perbedaan, persetujuan tetap wajib untuk menyimpan.
5. Setelah simpan, `/`, `/kontak`, footer publik mengikuti nilai baru; domain sosial dibatasi, XSS di-escape, URL unsafe ditolak.
6. Dua tab stale editor: tab lama wajib HTTP 409. Actor admin biasa/peserta wajib ditolak. Paket dan hak CAT/QQ/Kelas tidak berubah.
7. Periksa fokus keyboard, tab order, tautan, overflow nilai URL panjang, perubahan desktop/mobile, dan pesan error.
8. Simpan bukti log test/build beserta waktu eksekusi; screenshot FAIL undangan lama vs log PASS baru tidak memiliki urutan berbasis timestamp yang terkonfirmasi.

**QA di lingkungan pembuat installer:** lint/smoke statis dan pemeriksaan hash saja; tidak ada klaim PHPUnit Windows/MariaDB owner sudah dijalankan. Release CSP staging, restore independen termasuk media, legal, SMTP/Google operasional, dan sign-off tetap OPEN.
