# Siapegawai v1.1.1 — Refresh status akun sebelum akses privat

## Gejala

S6.1 v1.1 gagal pada test `superadmin can suspend and restore with reason and audit without changing history`: respons akses katalog tryout setelah suspend 200, semestinya 403.

## Diagnosis

`actingAs($participant)` pada Laravel Feature Test mempertahankan model autentikasi yang sama di memori, padahal layanan admin mengubah status melalui instance database lain. Middleware `EnsureActiveAccount` memeriksa atribut model ter-cache dan tidak membaca status terkini. Dalam request browser normal Laravel umumnya memuat ulang user dari session, tetapi tidak tepat bergantung pada asumsi tersebut untuk keamanan akun.

## Perbaikan

- Semua route yang sudah memakai `EnsureActiveAccount` memeriksa keadaan akun di database pada setiap request privat.
- Atribut role/status autentikasi pada request disinkronkan sehingga middleware role berikutnya juga memakai nilai terbaru.
- Pending/rejected/suspended ditolak menurut kebijakan sebelumnya; status pending pada dashboard tetap diarahkan ke halaman aktivasi.
- Tes tambahan: sesi peserta aktif yang ditangguhkan, pemulihan akses setelah restore, dan perubahan role/status admin.

## Cakupan

Tidak ada migration, data delete, CSS/JS, perubahan UI, ataupun perubahan pada service suspend/restore. Ini bukan sistem revokasi token lintas perangkat untuk API; cakupannya route aplikasi yang dilindungi `EnsureActiveAccount`.
