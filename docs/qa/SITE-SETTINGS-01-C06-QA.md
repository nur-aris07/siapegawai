# SITE-SETTINGS-01 / Fase C / C-06 — QA UX dialog dan layout

Status: REVIEW, belum OWNER-ACCEPTED. Tidak melakukan Git otomatis.

## Baseline QA C-05 (laporan operator, 15 Nov 2026)

- C-05 apply/verify PASS, focused 16/240 PASS, full 245/2.300 PASS, Vite PASS, git diff --check PASS.
- UAT pemilik C-05: Tambah/Edit menyebabkan navigasi/reload ke query string; dialog tampak di kiri atas. Panel 1100px meninggalkan kanan kosong dan tombol Lihat website jauh dari tabel.

## Perubahan C-06 (UI saja)

- Tambah/Edit tetap punya URL GET sebagai fallback non-JS/deep link. Bila JS tersedia, klik biasa dicegat dan editor Blade hasil GET di-fetch sebagai HTML dari origin sama, lalu ditempatkan dalam dialog native `showModal()`; URL tetap saat modal dibuka dan dokumen tidak reload. Hanya tepat sebelum form POST, History API menyiapkan URL fallback `?create=1`/`?setting=...` agar redirect validasi Laravel kembali ke editor yang sama (tanpa mengubah kontrak server). Memakai server-rendered hidden version/CSRF/form untuk menjamin validasi POST lama tidak berubah.
- Status gagal mengambil editor ditampilkan inline (dan toast bila tersedia). Link dengan Ctrl/Cmd/Shift/Alt tetap mengikuti perilaku browser.
- Perbaiki CSS native modal: fixed centered, overlay browser, responsive, focus input, Escape, Tutup/Batal, pemulihan fokus. GET langsung tanpa JS tetap merender dialog form.
- Pindahkan Lihat website ke toolbar sejajar Tambah, lebarkan panel mengikuti workspace (bukan max-width 1100px). Tidak ada fitur modul lain berubah.

## Acceptance / perintah QA owner

1. Dry-run/verify installer dan periksa hash serta HEAD expected `75afa10c94adfc34dfa0134860e781d15a0605c5`; backup tersimpan sebelum writes.
2. `php artisan optimize:clear && php artisan view:cache && php artisan view:clear && php artisan test tests\Feature\SiapegawaiSiteSettingsTest.php && php artisan test && npm run build && git diff --check && git status --short --branch`.
3. UAT desktop browser: di `/admin/website` buka Tambah dan Edit; address bar **tidak** berubah selama editor dibuka (sebelum submit), halaman **tidak** berkedip/reload; dialog muncul **di tengah** viewport, overlay dan latar inert; nilai/kunci tampil benar, fokus pada field.
4. UAT keyboard: Tab, Shift+Tab, Escape, Tutup, Batal; setelah close fokus kembali pada pemicu. Form invalid tetap memperlihatkan errors di fallback server, preview/confirm tetap berjalan sebagaimana C-05.
5. UAT responsive: viewport 1440, 1024, 768, 375, 320; toolbar tetap terbaca; tabel scroll horizontal jika perlu, tanpa page overflow; modal tidak melampaui viewport.
6. UAT browser network: klik Tambah/Edit menghasilkan request **GET HTML** tanpa reload (bukan XHR DataTables JSON); response 403/session expiry tidak menampilkan dialog palsu, melainkan error.
7. Pastikan Hapus tetap disabled, data custom tidak otomatis tampil publik; superadmin-only, audit, cache, konflik versi, legal/SMTP/paket tetap scoped.

Belum ada QA Windows/DB lokal yang dijalankan oleh asisten; offline browser harness terpisah bukan bukti website Laravel pemilik.

## Pra-penyerahan offline C-06 (asisten, bukan environment owner)

- PHP lint installer dan tes PASS; JS syntax `node --check` PASS.
- Installer smoke dengan baseline fixture C-05: 18 prerequisite SHA, dry-run tanpa write, konflik manifest/prasyarat/backup menolak, backup sebelum UPDATE, apply 5, verify, apply ulang idempoten PASS.
- Chromium headless DOM dengan server respons tiruan: klik Tambah/Edit tanpa navigasi, modal native benar-benar `:modal` dan pusat viewport 1280×800, input menerima fokus, Escape dan Tutup mengembalikan fokus, URL fallback baru disiapkan saat submit, tak ada JS runtime error. **Bukan uji browser aplikasi owner.**
- Teridentifikasi dan diperbaiki dalam pra-penyerahan: dialog hasil `DOMParser` berasal dari dokumen inert; wajib `document.importNode(dialog,true)` sebelum `showModal()` agar tidak melempar `InvalidStateError`.
