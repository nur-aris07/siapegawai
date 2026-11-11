# PKG-OPS-ENT-MIN — Catatan Implementasi Minimal v1.2

**Baseline source:** `cbc8abf2fd902ee3ba16b696316b95dee31fdebe` (QQ-CORE-01).  
**Status:** Source dan test disiapkan; hasil MySQL/PHPUnit serta visual UAT **menunggu bukti Windows**. Tidak ada akses pengerjaan peserta diaktifkan pada tahap ini.  
**Acuan produk:** Feedback Final Review QQ-SPEC pemilik, `QQ-SPEC-ADR-v1.1.md`, `PKG-OPS-ENT-QQ-CONTRACT-v1.1.md`, serta Design System & Visual Contract.

## Batas implementasi

1. `qq_quota_policies` memuat manfaat dasar kode `FREE` (berlaku seluruh peserta aktif) atau `grant` (diberikan eksplisit kepada peserta via `access_grants`). **Tidak ada nilai kuota default, seeder produk, ataupun aktivasi otomatis.** Kode seperti nama paket baru efektif jika dibuat/disahkan melalui konfigurasi Superadmin.
2. `qq_daily_quota_counters` unik `(user_id, bucket_date_wib)`; reset harian adalah pergantian tanggal `Asia/Jakarta` secara alami, bukan menghapus ledger. `qq_quota_usages` menyimpan idempotency key, referensi attempt, policy, dan snapshot limit yang dipakai saat start.
3. `QuickQuizQuotaService::remaining()` dan `::resolve()` hanya membaca data. Manfaat tertinggi yang masih aktif berlaku; grant `member` untuk aktivasi akun, CAT, dan kelas tidak diperlakukan sebagai quota QQ.
4. `::startWithQuota()` menyediakan seam transaksi internal: lock user, replay key tanpa debit, periksa policy, buat counter, panggil callback **yang wajib membuat attempt dan mengaktifkan soal pertama DI TRANSAKSI DATABASE YANG SAMA**, lalu buat usage dan increment counter. Tidak ada route yang memanggil seam ini sebelum QQ-CORE-02. Jangan menggunakan panggilan eksternal, jaringan, atau side effect selain database di callback.
5. Panel `admin/quiz-kilat-kuota` khusus Superadmin aktif: create/update policy + pemberian/pencabutan grant, audit alasan, waktu input WIB dikonversi ke UTC. Daftar policy yang kecil menggunakan Blade; **riwayat grant menggunakan Yajra di route `index()` yang sama**, dengan fallback Blade dan AJAX server-side (pencarian dan filter tanpa reload). Grant historis tetap disimpan.
6. Kebijakan limit yang berubah di tengah hari tidak menghapus `consumed_count` terdahulu. Unlimited hanya berlaku untuk policy dengan flag `unlimited=true` dan limit `NULL`. Tidak ada refund otomatis atau payment.

## Kewajiban penggabungan dengan QQ-CORE-02

- **Jangan** expose `startWithQuota` ke endpoint umum yang menerima `attempt_ref` buatan klien. Callback harus membuat `qq_attempts` yang tersimpan, memeriksa `quick_quiz_versions` terbit dan current/arsip, menerbitkan soal pertama, lalu mengembalikan referensi server. Pada stage tersebut tambahkan FK langsung dari usage ke attempt (`qq_attempt_id`) setelah tabel attempt tersedia.
- Pastikan resume *attempt aktif* memakai lookup server berdasarkan peserta + quiz sebelum debit baru, termasuk bila idempotency key berganti. Kunci pada user dan lock row attempt/counter yang sama menjamin tab serentak tidak mendebit dua kali untuk satu attempt.
- Tangani timeout/login expiry, penggunaan kuota pada perubahan tanggal WIB, benturan retry, dan snapshot review terpisah dari CAT/S7.3. Pembahasan 3 detik dan review memakai versi soal yang sama.
- Lakukan integration test **real concurrent** di MySQL/MariaDB testing, tidak menganggap simulasi retry berurutan sebagai bukti race-free.
- Kuota produksi untuk FREE/Pejuang/AbdiNegara/VIP **belum ditetapkan pemilik**. Pengisian angka yang benar adalah operasi Superadmin berdasarkan keputusan produk, bukan angka dari installer ataupun engineering.

## Batas penerimaan

- Build/route/view tests dan PHPUnit harus PASS di Laravel Windows.
- UAT halaman Superadmin desktop 1440/1366 dan mobile 375/320, border-spacing, keyboard, panjang tabel, dan form error; cek CSP dan output escaped. Tidak ada stat sukses visual otomatis.
- Sebelum Git: pastikan staging hanya file PKG-OPS-ENT-MIN, `.env.siapegawai.example` tidak disertakan, installer dan backup lokal tidak disertakan.
