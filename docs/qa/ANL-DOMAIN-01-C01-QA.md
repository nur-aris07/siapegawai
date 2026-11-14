# ANL-DOMAIN-01 / C-01 — QA dan scope final

**Baseline:** `be78802c520896369ef9d5f4fdd9e3ae6ea2147d`; **work ID:** `ANL-DOMAIN-01`.
**Scope owner:** hub deskriptif CAT, Kelas, Quiz Kilat + penataan navigasi tanpa menghapus route riwayat/detail lama. Desain participant workspace menurut `DESIGN-SYSTEM-AND-VISUAL-CONTRACT.md` dan mockup Rev.01. Tidak ada migration, data tulis, skor lintas domain, threshold mastery, prediksi CPNS, atau rekomendasi otomatis.

## Preserved / Adapted / New

- **Preserved:** seluruh route dan isi riwayat CAT, halaman kelas, katalog/riwayat/analisis QQ, backend scoring/kuota, public v4 dan keamanan role.
- **Adapted:** navbar peserta mengganti tautan **Riwayat & progres** menjadi **Analisis kemampuan** (bukan menambah menu). Dashboard peserta membuka hub; halaman detail CAT, Kelas, dan QQ memberi jalur kembali ke hub. Tryout saya tetap memberi akses riwayat CAT; QQ/Kelas tetap memberi akses detail milik domain.
- **New:** `/analisis-kemampuan` dengan grafik CAT 5 sesi final, progress materi kelas enrollment aktif, ringkasan latihan kelas terpisah, dan grafik distribusi QQ memakai service yang sudah diterima.

## Perhitungan dan batas bukti

- CAT: hanya `completed` / `timed_out`, count, AVG dan MAX `score_percent`, 5 hasil final terakhir dalam urutan waktu; **tidak** memfinalisasi sesi atau mengklaim skor CAT resmi.
- Kelas: hanya `enrollments.status=active` (termasuk untuk menghitung latihan final dan menampilkan nama kelas); `MemberLearningProgressService::summaries` menghitung materi `published`. Tanpa kelas/materi tidak membuat 0% palsu.
- QQ: `QuickQuizAnalyticsService::forUser` tetap sumber angka resmi dashboard domain. Total locked mencakup timeout; respons rata-rata hanya answered; sesi abandoned/active tidak dihitung.
- Tiap query tersaring `user_id`, role `participant`+`active`, response pribadi `no-store`.

## Acceptance / UAT

1. `php siapegawai-update.php` → `--apply` → `--verify`; idempotensi dan backup konflik.
2. `php artisan optimize:clear`, PHP lint source, `php artisan test tests/Feature/SiapegawaiMemberDomainAnalyticsTest.php`, full `php artisan test`, `npm run build`, `php artisan route:list --name=member.analytics.index`, `git status`.
3. Browser desktop dan 320/375/768/1024/1440 px: tidak overflow; nav baru ke hub; CAT riwayat tetap dari Tryout saya dan hub; Kelas dan QQ tetap punya detail; grafik disertai teks/legend; keyboard focus, zoom 200%, CSP report-only QA saat tersedia.
4. Validasi actual/data kosong, kelas revoked, sesi CAT active, QQ abandoned, dan guest/admin/suspended via tests. **Browser UAT belum PASS sampai owner mengonfirmasi.**
5. Gate release staging/recovery P0 sebelumnya tetap OPEN. Git hanya setelah OWNER-ACCEPTED; OWNER sendiri menjalankan git add/commit/push.

## C-02 — Koreksi assertion Cache-Control (14 November 2026)

- **Temuan C-01:** installer 12/12 hash PASS, PHP lint PASS, Vite build PASS. Focused PHPUnit: 5 PASS / 1 FAIL. Satu kegagalan adalah perbandingan string header `Cache-Control` berurutan: test mengharapkan `private, no-store`, sementara response aktual `no-store, private`.
- **Analisis:** kedua directive tersebut sama-sama ada, dan urutan antar-directive `Cache-Control` tidak mengubah makna. Ini kegagalan assertion yang terlalu ketat, bukan indikasi kebocoran cache atau regresi domain analytics.
- **Perbaikan C-02:** uji dua directive `private` dan `no-store` secara independen pada hasil response yang sama. Tetap FAIL jika salah satunya hilang; tidak melonggarkan header backend.
- **Cakupan:** hanya file test dan catatan QA yang berubah; 10 file implementasi lain tetap. Tidak ada migrasi atau perubahan route/controller/service/CSS/UI.
- **Verifikasi yang diperlukan:** installer dry-run/apply/verify (`12/12`), focused `SiapegawaiMemberDomainAnalyticsTest.php` (6 test), full `php artisan test`, dan `git status`. Build Vite C-01 tidak perlu diulang bila tidak ada perubahan frontend.
- **Gate:** UAT browser owner tetap diperlukan sebelum `OWNER-ACCEPTED`; git add/commit/push tetap dikerjakan manual oleh owner, dan gate release P0 tetap OPEN.
