# Siapegawai v1.0 — S5.3 + S5.4

## S5.3 Snapshot materi historis

- Pada penerbitan template tryout, setiap item menyimpan `subject_id_snapshot`, `subject_code_snapshot`, `subject_name_snapshot`, `topic_id_snapshot`, dan `topic_name_snapshot`, di samping `question_version_id` yang sudah dibekukan pada S4.
- Analisis hasil/riwayat mengutamakan kode dan nama snapshot tersebut, sehingga pemindahan soal ke topik atau kategori lain tidak mengklasifikasikan ulang hasil lama.
- Migration `2026_11_08_000007` menambah lima kolom nullable dan melakukan **backfill** item yang sudah ada. Backfill menggunakan asosiasi kategori/topik yang *masih tersedia saat migrasi*; jika sebelumnya sudah dipindah, riwayat asli tidak dapat direkonstruksi otomatis. Backup DB diperlukan sebelum migrasi.
- Draft test fixture / data lama tanpa snapshot masih memiliki fallback eksplisit ke master supaya tampilan tidak langsung gagal. Template baru yang diterbitkan akan mempunyai snapshot.

## S5.4 Ketahanan CAT

- Perubahan jawaban dan tanda tinjau masuk antrean per soal. Permintaan dari tab yang sama untuk soal tersebut dikirim berurutan agar respons lama tidak mendahului revisi terbaru.
- Jika fetch gagal, perubahan tetap berstatus belum tersimpan dan dicoba lagi secara bertahap. Event `online`, kembali ke tab, atau tombol `Coba simpan lagi` memicu retry.
- Pengumpulan manual ditolak di sisi klien jika masih ada perubahan yang belum berhasil dikirim. Backend tetap menentukan deadline, validasi item, kepemilikan, dan hasil akhir.
- Jika waktu habis, antarmuka mengarahkan peserta ke halaman hasil ketika jaringan tersedia. Backend menyelesaikan percobaan yang kedaluwarsa pada permintaan pertama yang sampai ke server.
- Tombol navigasi dan pilihan tidak menyimpan token/password secara offline. **Jangan refresh atau tutup tab ketika offline dan terdapat perubahan belum tersimpan**; patch ini tidak memberikan jaminan penyimpanan setelah menutup tab tanpa koneksi.
- Perlindungan terhadap dua browser/perangkat yang mengubah soal sama secara serentak belum termasuk; memerlukan mekanisme revision/optimistic concurrency di backend sebelum layanan produksi.

## Penerapan

1. Back up MySQL development `siapegawai`.
2. Dry run: `php siapegawai-update.php`; pastikan `CONFLICT=0`.
3. Apply: `php siapegawai-update.php --apply --migrate --verify`.
4. Periksa `php artisan migrate:status` dan hasil PHPUnit, lalu coba CAT di browser dengan dua/lebih soal menggunakan DevTools Offline/Online.
5. Coba ubah kategori/topik pada master soal dalam database testing, pastikan analisis historis tetap membaca snapshot sebelumnya.

## Bukan cakupan rilis

Tidak mengubah skor menjadi bobot resmi, tidak mempercantik workspace, tidak mendesain ulang template Tryout, tidak membuat penyimpanan offline lintas sesi, dan belum melengkapi kontrol produksi seperti MFA dan CSP enforcement.
