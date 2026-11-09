# Siapegawai v1.1.2 — Koreksi 302 vs 403 pada dashboard peserta

## Gejala

Hotfix v1.1.1 memperbarui status akun pada setiap request, tetapi `SiapegawaiAccountStatusRefreshTest` masih menemukan HTTP 302 untuk peserta `suspended` yang membuka `/dashboard`, bukan HTTP 403.

## Akar masalah

`EnsureActiveAccount` mengarahkan **semua** peserta non-aktif pada route `dashboard` ke `registration.status`. Aturan ini diperlukan untuk `pending` yang menunggu aktivasi, **bukan** untuk `suspended` atau `rejected`.

## Perubahan

- Pertahankan pengecekan `fresh()` terhadap data MySQL.
- Dashboard peserta `pending` tetap diarahkan ke status aktivasi (302).
- Dashboard peserta `suspended` / `rejected` ditolak (403), sama seperti route tryout privat.
- Tes regresi mencakup redirect pending dan penolakan suspended/rejected, selain kasus sesi lama yang sudah ada.

## Cakupan dan batasan

Hanya middleware, file test, dan dokumentasi yang berubah. Tidak ada migration, perubahan tabel MySQL, CSS, JS, atau login. Patch tidak menambah kebijakan revokasi session/token untuk API di luar route yang memakai `EnsureActiveAccount`.

## Pengujian

`php artisan test --filter=SiapegawaiAccountStatusRefreshTest`

`php artisan test --filter=SiapegawaiParticipantOperationsTest`

`php artisan test`
