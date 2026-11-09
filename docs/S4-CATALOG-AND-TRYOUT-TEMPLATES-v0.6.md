# Siapegawai — S4.2 & S4.3 v0.6

## Scope

- `/admin/materi`: kelola kode/nama/urutan/status kategori; kode tidak dapat diganti setelah dibuat.
- `/admin/materi/{id}`: CRUD topik per kategori; slug stabil. Kategori/topik yang mempunyai soal tidak boleh dihapus. Menonaktifkan tidak menghapus relasi histori.
- `/admin/tryout`: daftar server-side Yajra pada URL HTML yang sama; pencarian soal terbit maksimum 25 hasil/request; susun urutan.
- Draft tryout: kode, nama, deskripsi, durasi (5–360 menit), batas percobaan (1–20), maksimal 100 soal.
- Soal yang dipilih menyimpan **question_version_id**, bukan hanya `question_id` atau versi terbaru. Draft dapat diedit. Paket yang telah diterbitkan tidak dapat diedit langsung.
- Publikasi menolak paket kosong dan paket yang memakai master soal diarsipkan. Arsip paket terbit tidak menghapus relasi ke versi soal.
- Audit: create/update/delete kategori dan topik, create/update/publish/archive tryout.
- Soal publik JSON demo, kelas, paket langganan, login, registrasi, engine CAT peserta tidak berubah.

## Penting tentang S5

Publikasi template hanya membuat susunan soal siap dipakai nanti. Tidak membuat route pengerjaan peserta, sesi, nilai, atau izin mengerjakan. Engine CAT peserta, snapshot bobot ujian, batas waktu berbasis server, autosave dan penilaian harus menjadi tahap S5 dengan pengujian tersendiri.

## Instalasi

1. Backup database MySQL development secara mandiri sebelum migration.
2. Taruh installer PHP di root Laravel.
3. `php siapegawai-update-v0.6.php` (dry run, tidak mengubah file).
4. Bila CONFLICT=0, jalankan `php siapegawai-update-v0.6.php --apply --migrate --verify`.
5. Log ada di `.siapegawai-backup/s4-catalog-tryout-v0.6-*/`.
6. Buka `/admin/materi` dan `/admin/tryout` setelah login sebagai operator/superadmin.

Jangan jalankan `migrate:fresh` atau `php artisan test` terhadap database utama. Installer menolak migrasi production dan memerlukan konfigurasi testing MySQL terpisah sebelum PHPUnit.

## Uji browser

1. Buka TWK → tambahkan topik baru, nonaktifkan, aktifkan kembali. Coba hapus topik yang digunakan soal: harus ditolak.
2. Buat template SIM-SKD-01 (misal 45 menit/2 percobaan), tambah TWK-001 v1, simpan sebagai draft.
3. Periksa kembali detail, versi dan urutan; terbitkan dengan checkbox review. Status menjadi Terbit.
4. Ubah soal master ke v2 dan terbitkan. Kembali ke template: tetap mengikat TWK-001 v1.
5. Periksa role peserta tidak dapat mengakses `/admin/materi` atau `/admin/tryout` termasuk AJAX.

## Batasan yang disengaja

- Tidak ada impor CSV/Excel, scoring SKD/TKP, review dua orang, duplikasi template terbit atau rollback paket.
- Belum membuka akses peserta ke paket yang diterbitkan. Batas percobaan disimpan sebagai metadata sampai S5.
- Mengarsipkan master soal dapat menghalangi publikasi draft paket yang memuatnya; paket terbit yang sudah ada tetap menyimpan versi historis.
- CSP ketat belum dipaksakan otomatis karena memerlukan pengujian aset keseluruhan. Tidak ada CSS/JS inline yang ditambahkan.
