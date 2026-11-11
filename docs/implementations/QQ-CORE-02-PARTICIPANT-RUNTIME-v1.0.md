# QQ-CORE-02 — Runtime Quiz Kilat Peserta v1.0

**Baseline Git:** `4d841a9e44d13da79abb5371b9f04a2decad0b70` (PKG-OPS-ENT package-based quota). **Tanggal:** 2026-11-11. **Status:** implementasi source disiapkan; QA lokal Laravel/MySQL dan UAT masih wajib.

## Scope produk yang dilaksanakan

- Katalog dan detail Quiz Kilat hanya menampilkan quiz terbit; peserta melihat paket akun **Pejuang, Abdi Negara, atau VIP**, batas kuota WIB hari ini, pemakaian dan sisa yang benar-benar diambil dari `QuickQuizQuotaService::remaining()` tanpa membuat debit atau counter.
- Start peserta menggunakan kebijakan kuota sah yang disetujui Superadmin; **tidak ada angka kuota produksi hardcoded**. Start dan penerbitan soal pertama berada dalam satu transaksi database bersama debit harian. Tidak memakai `tryout_attempts`, `class_practice_attempts`, atau penilaian CAT.
- Snapshot `quick_quiz_version_id` dipin saat start (versi quiz dan `question_versions` published). Setiap soal punya deadline dari server sesuai `seconds_per_question`. Browser tidak berwenang memperpanjang timer atau mengubah nilai.
- Memilih satu pilihan langsung mengirim dan mengunci jawaban; keterlambatan diproses timeout. Server mengunci satu jawaban per item. Feedback berisi **kunci dan pembahasan yang sama dengan versi soal terbit**, tampil selama tiga detik. **Soal berikutnya hanya diaktifkan setelah request lanjut** sehingga koneksi putus tidak menghabiskan timer soal yang belum ditampilkan.
- Pemulihan menggunakan satu attempt yang sama selama tidak melewati **30 menit sejak aktivitas server terakhir**; apabila terlewati, attempt ditandai `abandoned`, kuota yang sudah dipakai tidak otomatis dikembalikan, review hanya memperlihatkan soal yang sudah disajikan dan dikunci. Login sesi aplikasi tetap diperlukan untuk mengakses kembali.
- Skor `correct_count / total_questions × 100` dan status `completed/abandoned`; jawaban salah atau timeout mendapat nol. Peserta dapat melihat riwayat/review tanpa timer; peserta lain tidak dapat melihat/mengubah hasil.

## Keputusan engineering terukur

1. **Satu attempt aktif per peserta, lintas judul**, untuk mencegah debit ganda dari dua tab. Start menggunakan user-row lock yang sama dengan kuota. Permintaan ulang dengan idempotency key yang sama atau key berbeda saat attempt aktif kembali ke attempt lama, bukan debit baru.
2. **Pemulihan feedback secara eksplisit:** request `next` hanya diterima setelah 3 detik server dan memicu timer soal selanjutnya. Saat browser reconnect, feedback terakhir tetap bisa dibaca tanpa kehilangan kunci/pembahasan.
3. **Tidak ada refund otomatis** sesudah start dan penerbitan soal pertama berhasil. Penyesuaian kuota manual/refund bukan scope tahap ini dan perlu kebijakan bisnis tersendiri.
4. **Revisi atau arsip quiz tidak mengubah attempt yang sudah dimulai**; attempt baru hanya untuk quiz `published` terbaru dan melalui pemeriksaan kuota. Akun suspend tetap dilarang melalui middleware.
5. **Tidak perlu AJAX polling agresif/worker/queue**; perubahan state dilakukan lewat request yang idempotent dan transaksi DB. JavaScript hanya memberi countdown, auto-lock pilihan, dan transisi feedback. Tanpa JS, form submit/lanjut tetap dapat digunakan.

## Data baru

Migration `2026_11_11_000017_create_quick_quiz_attempts.php` membuat dua tabel saja:

- `quick_quiz_attempts`: relasi peserta–versi quiz–ledger quota; status, fase, nomor soal, timer, window pemulihan, jumlah soal, nilai, dan audit timestamp.
- `quick_quiz_attempt_answers`: relasi attempt–pinned item, pilihan nullable, status timeout/answered, benar/salah, waktu respons milidetik dari server, dan waktu kunci. Unique `(attempt_id, item_id)` mencegah double answer.

**Data lama tidak dihapus**, tidak ada migrasi ke tabel CAT atau Latihan Kelas, dan tidak ada seeding angka paket.

## Acceptance minimum sebelum Git/aktivasi nyata

- `npm run build`, route list, `view:cache`, feature test QQ-CORE-02, kemudian full regression. Gunakan DB **`siapegawai_testing`**, bukan development/production.
- Buktikan start tanpa policy tidak membuat ledger/attempt; kuota=0 menolak; kuota 1 menghasilkan satu debit dan tab/retry kedua kembali ke attempt; limit harian WIB akurat pada halaman peserta.
- Buktikan jawaban satu klik langsung terkunci, pengiriman ganda tidak mengubah jawaban, deadline ditentukan server, timeout satu soal tidak menghabiskan soal berikutnya, feedback tiga detik, reload ketika feedback dan pemulihan.
- Buktikan semua 30–40 soal selesai dengan skor biner yang benar, review versi lama setelah quiz revisi/arsip, akses lintas peserta 403, dan tidak ada perubahan CAT maupun progres S7.2/S7.3.
- **Concurrency MySQL nyata**: dua POST mulai bersamaan dari dua sesi browser/process dengan satu kuota tersisa harus menyimpan tepat satu ledger + satu attempt. PHPUnit sequential idempotency tidak cukup membuktikan keamanan race.
- UAT desktop dan mobile terhadap Design System/Visual Contract: spacing antarseksi, pilihan tombol fokus keyboard, timer, feedback tidak terpotong, riwayat dan empty states. Tidak dianggap PASS hanya oleh PHPUnit.

**Gate produksi**: hanya aktifkan runtime bagi peserta jika kebijakan kuota nyata tiap paket yang relevan telah disahkan dan diatur. Katalog dapat dibuka untuk peserta aktif tanpa kuota terkonfigurasi, tetapi tombol mulai harus terkunci.
