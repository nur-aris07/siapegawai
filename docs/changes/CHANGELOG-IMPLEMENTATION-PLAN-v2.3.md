# Siapegawai — Daftar Perubahan Review Implementation Plan v2.3

> Status: **APPROVED (perencanaan) — Git publication pending**, 10 November 2026 (WIB). Basis perbandingan: `IMPLEMENTATION-PLAN-v2.2.md` (8 November 2026).  
> Revisi ini hanya dokumen; **telah disetujui pemilik produk, belum Git commit/push**.

| Lokasi v2.3 | Jenis | Perubahan | Alasan / batas |
|---|---|---|---|
| Header, bagian awal | Diperbarui | Status review dan bukti historis v2.2 dibandingkan laporan v1.4/commit 9 Nov | Mencegah angka 11 tes/54 assertions dianggap baseline terbaru; SHA remote lokal masih unverified |
| Rekonsiliasi setelah header | Ditambah | Alias `CLS-QUIZ` → **`CLS-PRACTICE`**, S8 historis (`REL`) vs S8 roadmap (`CAT-ADV`) | ID stabil tanpa merombak histori |
| §1.4 | Ditambah | Definisi empat domain: CAT, Latihan Kelas, Quiz Kilat, Analisis Kemampuan | Menjaga batas fitur dan pemisahan hasil |
| §7.6 | Ditambah | Ringkasan S7.3 Latihan Kelas dengan timer sesi, enrollment, pinned versions | Rancangan lama tetap relevan; detail D-01–D-06 masih terbuka |
| §7.7 | Ditambah | Model A Quiz Kilat: admin curated; 30–40 soal target, timer individual, jawaban lock, feedback 3 detik, review akhir, kuota bersama harian | Keputusan konsep produk saja, bukan DDL/route/implementasi |
| §7.8 | Ditambah | Analisis domain-specific dan integrated analysis lanjutan | Tidak mencampur skor CAT, kelas, dan QQ menjadi metrik tunggal tanpa validasi |
| §8.7 | Ditambah | Isolasi penyimpanan hasil/attempt dan kebutuhan waktu respons QQ | Schema final dan debit kuota ditunda sampai fase `QQ-SPEC` |
| §10.7 | Ditambah | Arahan feature-first `ClassPractice` dan `QuickQuiz` | Tidak merefactor CAT yang sudah stabil |
| §13.3–§13.5 | Ditambah | Roadmap terbaru dan dependensi entitlement QQ, rename pekerjaan dan gate S7.2 | Fitur QQ di luar S7.3; angka tahapan baru belum ditetapkan |
| §14.2 | Ditambah | Acceptance dan pengujian per domain, plus gate Git/handover | Commit tidak menyubstitusi tes/UI/security |
| §17A–§17C | Ditambah | Keputusan terkunci, keputusan terbuka, tingkat keyakinan bukti | Membedakan keputusan produk dan asumsi teknis |
| §18–§20 | Ditambah | SOP Git per tahap, read-only status checks, API branch verification, template handover, sumber rujukan | Acceptance terlebih dahulu, Git commit & push baru setelahnya |
| §4, §12, tabel S3 | Koreksi eksplisit | Arahan MFA lama diselaraskan dengan keputusan **tanpa OTP/MFA** | Bukan pengurangan kontrol keamanan dasar; audit, rate limit dan sesi tetap wajib |

## Dipertahankan

- Prototipe visual v4, kontrak desain, empat shell `public/account/workspace/admin`, Blade/Vite dan pola feature-first.
- Empat aktor, login email/password, status akun, kontrol server, CSRF/otorisasi, audit, dan isolasi database testing.
- Bank soal berversi dan engine CAT beserta snapshot, hasil dan histori, tidak diubah untuk memenuhi fitur baru.
- Kelas dan materi teks v1.4, progres completion materi, dan temuan verifikasi browser regrant yang masih terbuka.
- Rencana `CLS-MON`, `CLS-MEDIA`, `CAT-ADV`, `PKG-OPS`, dan release-readiness; tidak diam-diam dinyatakan selesai.
- Payment gateway, OTP/MFA, generator soal otomatis QQ, serta redesign besar tetap di luar scope saat ini.

## Status pemeriksaan dan review

- [x] Salinan Implementation Plan v2.2 dibuat sebagai v2.3 lengkap, dengan penambahan bertanda dan koreksi terbatas.
- [x] Roadmap baru dipisah dari laporan status/roadmap historis v1.4.
- [x] `CLS-PRACTICE` dan `QQ-CORE` tidak dipakai bergantian untuk dua model pengerjaan berbeda.
- [x] Aturan feedback Quiz Kilat **tetap 3 detik** dan analisis waktu respons tercatat sebagai kebutuhan masa depan.
- [x] SOP commit/push/verifikasi/handover dicantumkan, tanpa menjalankannya sekarang.
- [x] Persetujuan isi dokumen oleh pemilik produk pada 10 November 2026.
- [ ] Rekonsiliasi `git status`, HEAD lokal, `origin/main`, dan SHA GitHub API yang dapat dibaca. Commit `df190d4` telah dikonfirmasi tersedia di GitHub, tetapi bukan bukti HEAD kini.
- [ ] Penempatan file dokumen di tree repository yang disetujui serta inspeksi diff dan file rahasia.
- [ ] Git commit, push, dan verifikasi SHA setelah approval.
- [ ] Penutupan browser S7.2 dan pembahasan teknis S7.3 Latihan Kelas.

**Kunci review:** revisi ini **tidak menyatakan** bahwa S7.3 sudah dibangun, Quiz Kilat sudah dirilis, atau GitHub telah diperbarui.
