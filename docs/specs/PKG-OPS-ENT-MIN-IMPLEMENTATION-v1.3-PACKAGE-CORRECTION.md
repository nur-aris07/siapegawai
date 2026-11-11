# PKG-OPS-ENT-MIN v1.3 — Koreksi manfaat Quiz Kilat mengikuti paket peserta

**Sumber keputusan:** Klarifikasi pemilik produk 11 November 2026.  
**Baseline target:** `cbc8abf2fd902ee3ba16b696316b95dee31fdebe` + source PKG-OPS-ENT-MIN v1.10 + hotfix visual v1.10.1, **belum di-commit**.  
**Status:** Coding/installer disiapkan; PHPUnit/MySQL dan browser QA setelah koreksi **belum diverifikasi**.

## Kontrak bisnis yang berlaku

1. Hanya tiga paket: **Pejuang (gratis)**, **Abdi Negara (berbayar)**, **VIP (berbayar tingkat lebih tinggi)**. Tidak ada tier produk `FREE` terpisah dari Pejuang.
2. Seluruh peserta **aktif** memperoleh paket dasar Pejuang tanpa grant QQ. Paket berbayar mengikuti keanggotaan **akun peserta**, bukan judul Quiz Kilat.
3. Nominal kuota per paket belum diputuskan. `member_plans` mengisi **identitas paket saja**; **tidak ada** nominal kuota, harga, masa berlangganan, atau entitlement berbayar yang di-seed. Nilai disahkan melalui Superadmin dan baru menjadi efektif jika `qq_quota_policies` terkait paket berstatus aktif dengan angka sah atau unlimited eksplisit.
4. `QuickQuizQuotaService::remaining()` menampilkan `plan_code`, `plan_name`, `daily_limit`, `consumed`, `remaining`, `unlimited`, dan `bucket_date_wib` untuk UI peserta di `QQ-CORE-02`; **tidak ada route attempt peserta dalam tahap ini**.
5. Kuota lintas judul, reset 00.00 WIB, satu debit ketika attempt + soal pertama tersimpan, counter harian dan ledger idempotent, serta tidak menjumlah quota tetap berlaku.
6. Grant legacy `access_grants.grant_type=qq_quota` dan `qq_quota_policies` lama **tidak dihapus**, tetapi **tidak lagi menentukan hak penggunaan baru**. Pemakaian historis tidak di-reset. Jangan migrasikan nama paket/limit dari grant QQ lama secara otomatis.

## Model data minimum

- `member_plans`: tiga identitas bisnis (`PEJUANG`, `ABDI_NEGARA`, `VIP`) dan urutan tier; terpisah dari `tryout_packages` yang merupakan susunan soal CAT.
- `access_grants` yang **sudah ada**, digunakan sebagai status kepemilikan **paket** peserta: `grant_type=member_plan`, `resource_type=member_plan`, `resource_id=member_plans.id`. Ini bukan grant Quiz Kilat. Tidak diperlukan tabel checkout/pembayaran baru.
- `qq_quota_policies.member_plan_id` (nullable + unique + FK) mengikat kuota ke paket. Record lama dengan `member_plan_id=NULL` tetap tersimpan demi riwayat; hanya `scope=plan` dan FK paket yang berlaku untuk resolver baru.
- `qq_daily_quota_counters` dan `qq_quota_usages` tetap, termasuk FK dan snapshot historis. Perubahan paket di hari yang sama tidak menghapus pemakaian.

## Alur admin dan operasi

- Superadmin → **Manfaat Quiz Kilat** hanya menampilkan **tiga paket** beserta form angka kuota/status. Form “Grant manfaat peserta”, endpoint grant/revoke QQ, dan daftar grant per Quiz Kilat tidak lagi tersedia.
- Admin → **Direktori peserta → Detail peserta** memperlihatkan paket saat ini dan nilai kuota hari ini. Superadmin dapat mencatat perubahan paket setelah ada dasar verifikasi administrasi nyata, dengan alasan, waktu akhir opsional, konfirmasi, serta audit. Perubahan paket tidak otomatis berarti pembayaran sudah tervalidasi oleh sistem; SOP pembayaran manual tetap terpisah.
- Pejuang berlaku secara implisit bagi peserta aktif yang tidak memiliki paket berbayar aktif. Paket berbayar kedaluwarsa/dicabut kembali ke Pejuang. Ketika ada lebih dari satu keanggotaan aktif tak sengaja, manfaat terkonfigurasi tertinggi dipilih tanpa menjumlah. Workflow perubahan paket mencabut keanggotaan aktif lama terlebih dulu secara transaksional.
- Nilai kuota harus diisi Superadmin dari keputusan bisnis final, **tanpa placeholder yang secara diam-diam dipakai sebagai nilai produksi**.

## Integritas dan validasi

- `User::lockForUpdate()` digunakan saat perubahan paket maupun debit kuota untuk mencegah benturan transaksi. Ledger idempotency dan counter per `(user_id, bucket_date_wib)` tidak berubah.
- Menampilkan kuota tidak mendebit maupun membuat counter. Callback start attempt harus menyimpan attempt dan soal pertama dalam transaksi DB yang sama, hanya pada `QQ-CORE-02`.
- Akses peserta yang tidak aktif tetap ditolak. Data CAT, Latihan Kelas, completion S7.2, dan histori versi materi tidak disentuh.
- Legacy policy/grant tetap ada namun tidak lagi dievaluasi; jika data lama pernah dikonfigurasi, Superadmin **harus** memasukkan kuota paket baru secara eksplisit. Jangan mengklaim migrasi itu mempertahankan hak produktif lama tanpa review bisnis.

## Gate pengujian

1. Migration baru `2026_11_11_000016_link_qq_quota_to_member_plans.php` hanya setelah backup database development.
2. PHP lint, `route:list`, `view:cache`, Vite build, `SiapegawaiQuickQuizQuotaTest`, dan full PHPUnit.
3. UAT desktop/mobile: tiga kartu paket, spacing admin, pemberian paket dari detail peserta (bukan QQ), perhitungan sisa hari ini, status tidak dikonfigurasi, error form, revoke/kedaluwarsa, dan pemeriksaan otorisasi Superadmin.
4. **Race test MySQL sungguhan** masih wajib pada integrasi QQ-CORE-02; tes sequential idempotency bukan pembuktian race-free antar-proses.
5. Tidak mengaktifkan route participant attempt sampai quota dan engine runtime teruji; jangan commit sebelum UAT/error review yang relevan.
