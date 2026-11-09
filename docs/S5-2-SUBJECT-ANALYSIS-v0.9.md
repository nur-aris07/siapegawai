# Siapegawai S5.2 — Analisis hasil per materi (v0.9)

## Ruang lingkup

Patch ini menambahkan ringkasan per materi pada halaman hasil satu sesi dan pada riwayat seluruh percobaan selesai milik peserta. Kategori mengikuti master TWK/TIU/TKP/SKB. Indikator yang dihitung: total soal, benar, salah, tidak dijawab, dan persentase ketepatan. Tidak ada migration dan tidak ada penambahan jalur akses publik.

## Aturan hitung

- Hanya percobaan `completed` atau `timed_out`. Sesi `in_progress` tidak dihitung.
- Semua butir dalam `tryout_package_items` dihitung, termasuk jawaban yang tidak disimpan.
- Kunci jawaban diambil dari `question_options` milik **`tryout_package_items.question_version_id`** (pinned), bukan dari `questions.current_version_id`.
- `answered` adalah jumlah butir dengan `answer_label` tidak null; `correct` cocok dengan kunci pinned; `wrong=answered-correct`; `unanswered=total-answered`.
- Ketepatan `correct / answered * 100`; tanpa jawaban, ditampilkan `—` bukan `0%`. Nilai total sesi (skor satu poin) tidak berubah.
- Pada riwayat keseluruhan, pertanyaan yang muncul pada percobaan berbeda dihitung pada setiap percobaan. Percobaan milik akun lain tidak diikutkan.
- Analisis adalah metrik latihan internal, bukan pembobotan resmi TWK/TIU/TKP/BKN.

## Batasan data lama

Versi konten dan kunci dibekukan melalui `question_version_id`. **Penempatan materi/topik pada skema saat ini berada di master `questions.topic_id`**, bukan dalam snapshot versi/paket. Bila admin memindahkan soal ke kategori lain setelah ujian, pengelompokan historis per kategori berpotensi berubah. Sebelum pemakaian produksi, kategori/topik perlu disnapshot saat paket diterbitkan, atau perpindahan kategori soal yang sudah digunakan harus diblokir. Nilai benar/salah dari versi terbit tetap memakai kunci yang dibekukan.

## Validasi

1. Jalankan `php siapegawai-update.php` (dry run), lalu `--apply --verify` setelah tidak ada konflik.
2. Buka `/peserta/tryout/riwayat` sebagai peserta aktif yang mempunyai hasil. Periksa metrik per kategori.
3. Buka hasil percobaan dari riwayat. Pastikan kategori sesuai butir pada percobaan itu.
4. Cek akun peserta lain tidak bisa melihat hasil yang bukan miliknya.
5. Pengujian Laravel menggunakan MySQL `siapegawai_testing`; tidak pernah menjalankan `migrate:fresh` pada database aplikasi.

## File yang disentuh

- Service analisis baru dan regression tests baru.
- Member history/result controller dan view; satu partial Blade untuk tabel kategori.
- Copy Admin Workspace saja untuk status CAT aktif; tidak mengubah UX admin atau paket tryout.

Tidak ada perubahan pada engine autosave, timer, validasi soal, pembuatan template, database aplikasi, atau nilai historis.
