# PKG-OPS-ENT-MIN — Kontrak Entitlement dan Kuota Quiz Kilat v1.1

**Tanggal:** 11 November 2026  
**Sumber keputusan produk:** Feedback Final Review QQ-SPEC dari pemilik; `QQ-SPEC-ADR-v1.1.md` adalah sumber aturan fitur.  
**Status:** **READY FOR MINIMAL IMPLEMENTATION DESIGN**, bukan runtime deployed. Nilai manfaat paket belum disetujui sehingga *start peserta harus fail-closed*.

## Keputusan tetap dari pemilik produk

- Peserta aktif termasuk akun gratis dapat menggunakan QQ **hanya jika** limit kuota aktual terkonfigurasi dan entitlement cocok. Manfaat untuk Pejuang/AbdiNegara/VIP bersifat diferensiasi bisnis, **tanpa angka produksi saat ini**.
- Kuota **bersama semua judul** per peserta, per tanggal **Asia/Jakarta** (00.00–23.59 WIB). Membuka daftar/detail tidak memakai kuota.
- Debit persis **satu** ketika attempt baru berhasil dibuat **dan soal pertama diaktifkan**. Resume/duplikat attempt tidak debit lagi; pengulangan yang benar-benar menjadi attempt baru memakai unit baru.
- Banyak entitlement aktif: pakai limit **tertinggi**, bukan jumlah; manfaat `unlimited` hanya jika nilai benar-benar diaktifkan secara sah.
- Kuota dapat dikonfigurasi tanpa perubahan kode engine Quiz Kilat. Browser tidak dipercaya menghitung/menyimpan kuota.

## Kontrak API/domain untuk engineering

`resolve(user, instant_utc) -> {allowed, source_policy_id, daily_limit:int|null, unlimited:bool, bucket_date_wib, reason}`. `allowed=false` untuk user bukan participant aktif, grant expired/revoked, tidak ada kebijakan sah, atau konflik konfigurasi. `null` hanya bermakna tak terbatas ketika `unlimited=true` oleh policy tersimpan yang sah; `NULL` tanpa flag adalah **unconfigured**, bukan unlimited. Tidak ada default hardcoded untuk gratis.

`startOrResume(user, quiz_version, client_idempotency_key) -> {attempt_id, first_issued_at, deadline_at, quota_usage_id, remaining}`. Semua efek dibuat **dalam satu transaksi DB** dan diproteksi row-level lock (`SELECT ... FOR UPDATE`) plus unique constraints. Reconnect attempt aktif dikembalikan tanpa debit, bahkan ketika client mengirim key baru. `QuotaExceeded` atau `PolicyNotConfigured` harus menghasilkan tanggapan terarah; jangan membuat attempt tanpa debit.

`quotaRemaining(user, instant_utc)`: hitung limit tertinggi yang saat itu berlaku dikurangi total **pemakaian hari itu lintas seluruh judul**. Pergantian entitlement di hari yang sama tidak menggandakan saldo/ledger; sisa kuota dihitung ulang terhadap usage harian yang sudah terjadi. Jika entitlement berakhir di tengah attempt, attempt yang sudah dipotong dan dibuat sah boleh dilanjutkan selama aturan pemulihan, sedangkan attempt baru harus divalidasi ulang.

## Skema minimum yang direkomendasikan (untuk PKG-OPS-ENT-MIN)

- `qq_quota_policies`: konfigurasi manfaat yang terotorisasi (sumber gratis/paket/grant, limit integer >=0 atau flag unlimited, status active, berlaku-dari/hingga, approved_by, audit). **Tidak ada seeder nominal produksi**.
- `qq_daily_quota_counters`: unique `(user_id, date_wib)`, `consumed_count`, timestamp. Satu bucket lintas semua quiz; jangan reset dengan cron sebagai satu-satunya mekanisme—bucket dibuat/tentukan dari tanggal WIB saat start.
- `qq_quota_usage`: unique `attempt_id`, identitas user/bucket/policy, jumlah 1, waktu UTC; dapat diaudit dan diinspeksi. Jika desain stage memerlukan pending/reserved, idempotency juga wajib unik.
- `qq_attempts` dibuat di QQ-CORE-02, sehingga implementasi entitlement harus menyediakan antarmuka debit yang **siap dipanggil transaksi start**. Jangan memotong kuota dari UI admin/editor atau halaman detail.
- Validasi status user, ketentuan grant `starts_at <= now <= ends_at`, penguncian akses, `user_id` ownership; tidak hanya menyembunyikan tombol di Blade.

## Tes wajib sebelum mengaktifkan start peserta

1. Belum ada konfigurasi gratis/paket: start **ditolak**, tanpa potong kuota dan tanpa attempt.
2. Grant dua manfaat berbeda: pilih limit tertinggi, tidak dijumlah; grant dicabut/expired tidak dihitung.
3. Reset 00.00 WIB, terutama sekitar tanggal/midnight UTC; semua query memakai `Asia/Jakarta` untuk bucket, timestamps disimpan UTC.
4. Start pertama sah debit satu; reload, request duplikat, tab paralel tetap satu; retry setelah timeout jaringan tidak debit kedua.
5. Limit tercapai menolak start baru; attempt aktif dapat di-resume meski bucket sudah habis.
6. Perubahan entitlement di hari berjalan tidak memutihkan usage yang sudah tercatat.
7. Rollback database ketika gagal issue soal pertama tidak meninggalkan debit/attempt yatim.
8. MySQL/MariaDB concurrency nyata pada DB testing terisolasi; unit test tanpa konkurensi bukan bukti final.
9. Keamanan (guest/suspended/non-participant/id pengguna lain), CSP/CSRF, audit mutasi dan hasil.

## Pembatasan scope

Kontrak ini **tidak mewajibkan membangun checkout, payment gateway, invoice, seluruh PKG-OPS atau analytics**. Nilai limit paket/akun gratis perlu disahkan dan dimasukkan melalui pengelolaan policy terbatas oleh admin berizin sebelum pengerjaan peserta aktif. Refund otomatis tidak diaktifkan tanpa kebijakan produk tambahan. Spesifikasi ini tidak menyalakan route/member button QQ pada QQ-CORE-01.
