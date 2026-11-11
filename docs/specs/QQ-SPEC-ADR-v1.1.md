# QQ-SPEC v1.1 — Keputusan Produk Final dan Kontrak Implementasi Quiz Kilat

**Tanggal keputusan:** 11 November 2026  
**Status:** **FINAL untuk aturan produk di §1; engineering specification untuk §2–§6.** Tahap *editorial admin* boleh diimplementasikan sekarang. Aktivasi pengerjaan peserta hanya boleh dilakukan setelah entitlement/kuota sah dan teruji.  
**Supersedes:** `QQ-SPEC-ADR-v1.0.md` (historis) dan rekomendasi feedback pendek terpisah.  
**Baseline audit:** source Laravel 13 hasil rekonstruksi patch Git hingga `2742977b55ae480f620cc873fe937d9458e188d4` (parent aplikasi `c66e6e3`). Tidak mengklaim checkout langsung atau menjalankan test MySQL lokal pemilik.

## 1. Keputusan produk disahkan pemilik (bukan proposal engineering)

1. **Model A, admin-curated.** Admin menentukan judul, urutan **30–40 soal** dari bank soal published, serta **durasi per soal** pada level quiz. Tidak ada generator otomatis atau quiz yang dirakit peserta.
2. **Timer per soal**, bukan timer total. Ketika peserta memilih jawaban atau melewati deadline server, soal **terkunci permanen**. Tampilkan status (benar/salah/tidak dijawab), kunci, dan **pembahasan lengkap yang berasal dari `question_versions.explanation`** selama **3 detik**, lalu pindah otomatis. **Tidak ada field feedback pendek tambahan.** Review setelah sesi tidak dibatasi timer dan menggunakan sumber pembahasan sama.
3. **Versioning immutable.** Publikasi quiz mengunci versi quiz, urutan, durasi dan pinned published `question_version_id`. Perubahan setelah terbit menggunakan draft dan publikasi **versi baru**. Attempt selalu merujuk versi quiz & versi soal saat dimulai; riwayat/penilaian/kunci/pembahasan tidak berubah saat konten terkini direvisi.
4. **Scoring biner v1:** hanya soal dengan persis satu opsi benar, salah/timeout=0, hasil `(jumlah_benar / total_soal)*100`. Soal berbobot tidak boleh lolos validasi editor.
5. **Akses dan kuota:** seluruh peserta berstatus **active** termasuk gratis berpotensi menggunakan QQ, tetapi start hanya diizinkan ketika manfaat kuota harian yang **sah dan terkonfigurasi** telah tersedia. Kuota adalah **satu pool per peserta untuk semua judul**, bukan kuota per judul. Ketika beberapa entitlement aktif, pilih **angka tertinggi**, bukan akumulasi. Repetisi quiz boleh selama sisa kuota ada. Reset setiap **00:00 Asia/Jakarta**. Buka daftar/detail=gratis, **debit satu unit saat attempt berhasil dibuat dan soal pertama aktif**, resume/duplikat=tanpa debit tambahan.
6. **Nominal kuota TIDAK disahkan** untuk gratis, Pejuang, AbdiNegara, VIP Mentoring. Nilai (termasuk kemungkinan unlimited) harus dibaca dari kebijakan paket/config aktif yang disetujui; tidak boleh hardcode/seed angka contoh, mengada-adakan paket atau mengaktifkan kuota palsu. Jangan aktifkan start peserta saat profil manfaat belum valid.
7. **Recoverable:** attempt bisa dilanjutkan selama jendela pemulihan sekitar **30 menit** yang terpisah dari autentikasi. Waktu soal yang sudah aktif tetap berdasarkan deadline server; keterlambatan memfinalisasi hanya **soal aktif**, bukan memutar timer puluhan soal selanjutnya yang belum diterbitkan. Feedback yang terganggu ditampilkan lagi agar peserta dapat membacanya.
8. Pemisahan data QQ dari **CAT/Tryout, Latihan Kelas, materi/progres S7.2**. Tidak ada leaderboard, prediksi, gamifikasi, generator, payment gateway atau analytics lanjutan pada implementasi pertama.

## 2. Hasil audit source dan keputusan engineering

| Aspek | Fakta source / keputusan operasional | Batas keamanan |
|---|---|---|
| Bank soal | `questions.current_version_id`, `question_versions`, `question_options` sudah tersedia. Pilih versi `published` dan soal/kategori/topik aktif. | Pastikan 4–5 opsi, **tepat satu benar**, `score_weight IS NULL` semuanya, dan penjelasan tidak kosong. Pinned versi pertanyaan disimpan sebagai FK; histori versi soal tidak diubah. |
| Versioning | Contoh snapshot Tryout dan Latihan Kelas tersedia. | Dua level: `quick_quizzes` + `quick_quiz_versions` + item terurut. Versi terbit **tidak diedit**. `current_version_id` menunjuk versi berlaku; perubahan dilakukan pada draft baru, bukan update versi terbit. |
| Hak akses admin | Middleware `EnsureActiveAccount`, `EnsureRole` dan pola Policy sudah tersedia. | Semua route editorial admin saja. Form mutasi CSRF, konfirmasi publish, audit, validasi di server, rate limit. |
| Tabel admin | Yajra v13 sudah dipakai untuk daftar admin. | `index()` memberikan Blade untuk page request dan JSON Yajra untuk AJAX **di URL yang sama**. JavaScript via Vite, DOM pakai `textContent`, CSP aman. |
| Entitlement | `access_grants` hanya grant generik time-bound: **belum ada nilai kuota QQ harian.** | Perlu kebijakan kuota per paket yang valid, resolver max-benefit, dan counter/ledger satu pool lintas quiz. Jangan menyimpulkan izin dari record grant semata. |
| UI | `layouts.admin`, `admin-v1.css`, token navy/gold dan spacing serta Shell terpisah sudah tersedia. | Visual Contract & Checklist mengikat: 1366/1440 dan 375 mobile (cek 320/768/1024), keyboard/error/loading/empty, whitespace antar-panel, tanpa CSS inline. |

### Rancangan minimal penyimpanan

- `quick_quizzes`: code unik, status draft/published/archived, `current_version_id`, author, archive timestamp.
- `quick_quiz_versions`: quiz, version_number unik per quiz, status draft/published, judul, deskripsi, `seconds_per_question`, tanggal/aktor publish.
- `quick_quiz_version_items`: version, urutan unik, pinned question_version unik, snapshot kategori/topik, FK restrict delete.
- Nanti `quick_quiz_attempts` + `quick_quiz_responses` **terpisah**: pengguna, pinned version, status, posisi soal, issued/deadline/locked timestamp UTC, pilihan, lock_reason, server score, response_ms, quota_usage_id, last_activity/recoverable_until.
- Nanti quota ledger dengan unique `(user_id, quota_date_wib)` pada counter dan unique `(attempt_id)` pada debit; transaksi DB/row locking/MySQL-MariaDB; tidak ada browser-local quota sebagai otoritas.

### Semantik server untuk tahap pengerjaan (QQ-CORE-02)

1. **Start idempotent**: validasi peserta active, quiz published, entitlement active, kebijakan kuota aktif; dalam transaksi DB serialisasikan per peserta+tanggal WIB, ambil limit max valid, buat attempt dan soal pertama lalu debit persis sekali. Retry request harus mengembalikan attempt sama atau menolak dengan status jelas, bukan double debit. Permit renewal atau refund hanya bila ada kebijakan audit yang sah; default engineering **tidak ada refund otomatis setelah soal pertama diterbitkan**.
2. **Issue**: server hanya memberikan soal aktif dan pilihan tanpa kunci, serta server_now/deadline_at. Deadline dihitung UTC dari waktu issue; browser hanya countdown visual. Pindah ke soal berikut **setelah** fase feedback selesai; jangan membuat soal berikut atau memulai deadlinenya saat menilai soal sebelumnya.
3. **Lock**: transaksi mengunci baris attempt aktif; satu respons unik per attempt/item. Pilihan tepat waktu disimpan, skor ditentukan server, tidak bisa diperbarui. Deadline habis menjadi timeout/null, tidak menerima jawaban susulan. Request duplikat menerima keadaan terkunci yang sama. Buat tanda `feedback` yang tetap bisa diambil ulang.
4. **Feedback 3 detik**: kunci dan pembahasan versi pinned dapat diperlihatkan hanya untuk item yang sudah terkunci. Simpan state feedback dan waktu minimum; jika reconnect/refresh setelah interupsi, render ulang feedback sebelum mengizinkan issue berikutnya (server menegakkan minimal interval, UI mengelola 3-detik terlihat). Jangan memberi kunci item berikutnya.
5. **Recover**: jendela pemulihan default engineering **30 menit sejak interaksi terakhir** (berbeda dari session login). Jika soal aktif melewati deadline selama offline, kunci *hanya soal aktif* saat resume. Jangan menyimulasikan timeout untuk item berikutnya yang belum di-issue. Jika jendela berakhir, finalisasi status abandoned/expired terpisah, sisanya belum disajikan dan hasil diberi konteks jelas.
6. **Finish**: hasil persentase total quiz, jumlah benar/salah/tidak dijawab/soal belum tersaji dipisahkan, review semua item dari pinned question_version; redirect history berbeda dari CAT. Batasi AJAX/fetch ke owner/role aktif dan gunakan no-store cache untuk kunci.

Pemilihan guard timer admin **5–180 detik** dalam implementasi tahap pertama adalah pagar validasi teknis awal, **bukan** kuota bisnis. Bila rentang tersebut dinilai membatasi kebutuhan real operator, ubah melalui evaluasi UI/editor—bukan mengubah scoring atau data historis diam-diam.

## 3. Tahapan kerja yang bisa langsung dijalankan

**QQ-CORE-01 / ADMIN-EDITORIAL (paket implementasi ini):** migrasi tiga tabel editorial; CRUD draft; pilih & urut soal published; publish 30–40 valid dengan pinned versi; draft revisi versi berikut; arsip tanpa hapus; audit; daftar admin Yajra server-side; tes. **Tidak ada route/start kuota peserta**, tidak ada migration attempt, tidak ada angka manfaat fiktif.

**PKG-OPS-ENT-MIN:** manfaat per paket/akses gratis yang sah dan dapat dikelola (bukan contoh angka), resolver entitlement tertinggi dan aktif pada waktu request, counter harian WIB global, debit atomik/idempotent satu unit saat issue pertama, audit dan tes koneksi/konkurensi menggunakan DB testing. Kontrak terinci di `PKG-OPS-ENT-QQ-CONTRACT-v1.1.md`.

**QQ-CORE-02 / PARTICIPANT-RUNTIME:** katalog/detail baca-saja dengan jatah riil, start/resume, timer per item, feedback 3 s, result/review/history, tes retry/dua tab/revoke/expired/CSP dan browser UAT. Endpoint peserta **baru boleh diaktifkan setelah ENT-MIN terverifikasi**.

**QQ-ANALYTICS:** di luar implementasi awal.

## 4. Acceptance dan proses Git

- Semua tes lama tetap PASS; tambah feature test editorial (draft kurang dari 30 ditolak publish; 30/40 valid, 41 tidak; versi soal tidak aktif/berbobot/duplikat ditolak; publish confirmation; revisi terbit immutable; role/IDOR; Yajra same-route; XSS/escaping; arsip, audit).
- Jalankan migration pada **database dev setelah backup nyata**, bukan DB produksi. PHPUnit memakai DB khusus `siapegawai_testing`. `npm run build` lebih dulu dari PHPUnit bila ada entry Vite baru.
- Visual UAT: capture desktop 1440/1366 dan mobile 375, overflow 320/768/1024, jarak antar-panel, pencarian AJAX tanpa reload, keyboard, CSRF/CSP, empty/loading/error. Catat terpisah dari PHPUnit.
- Installer PHP wajib dry-run/hash/backup konflik fail-closed, `--apply`, `--migrate --db-backup-confirmed`, `--verify --tests`. Tidak stage `.env.siapegawai.example`, installer maupun folder backup. Commit dengan subject plus beberapa paragraf `-m`, lalu verifikasi SHA lokal/remote.

## 5. Batas keputusan yang belum otomatis disahkan

**Nilai kuota tiap paket/akun gratis, status unlimited VIP, dan manfaat entitlement riil** belum diberikan pemilik dan **tidak** diisi contoh produksi. Paket admin-editorial tetap aman untuk dikerjakan; paket start peserta harus tetap tertutup sampai parameter valid itu tersedia. Jika kelak ditemukan kebijakan yang mengubah hak akses, harga, jumlah kuota, scoring atau pengalaman timer utama, perubahan tersebut memerlukan persetujuan pemilik; bukan tiket optimisasi teknis.
