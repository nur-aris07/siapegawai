# Siapegawai — Referensi arah layout admin (draft untuk persetujuan)

**Status:** DRAFT / BELUM DIIMPLEMENTASIKAN. **Tanggal:** 8 November 2026.  
**Referensi visual:** screenshot INA-GOV yang dikirim pemilik. Referensi digunakan untuk tata letak dan pola operasional, **bukan** salinan identitas/merek INA-GOV.

## Prinsip utama

- Admin/Superadmin menggunakan workspace administratif, bukan layout publik. Tidak ada hero marketing, ilustrasi dekoratif, atau footer publik.
- Pertahankan identitas Siapegawai: Plus Jakarta Sans, navy `#0B1B40`, gold `#B88928`, border tipis, radius kecil, dan Tabler Icons. Jangan memakai biru INA-GOV sebagai warna merek otomatis.
- Utamakan **data dan pekerjaan**: filter, tabel, status, tindakan, umpan balik, empty/loading/error state. Tidak ada statistik fiktif dan tombol menuju fitur yang belum tersedia.
- Konsisten untuk role admin operasional dan superadmin tetapi menu tetap dibatasi otorisasi. Data pesertanya harus dilindungi middleware dan policy, bukan sekadar disembunyikan lewat CSS.

## Skeleton target

```text
┌──────────────────────────────────────────────────────────────────────┐
│ Topbar: logo Siapegawai                   nama / role / keluar       │
├──────────────────┬───────────────────────────────────────────────────┤
│ Sidebar ~240px   │ Ringkasan / Registrasi peserta                    │
│                  │ Keterangan 1 kalimat, aksi utama bila tersedia    │
│ Ringkasan        │                                                   │
│ Registrasi       │ [Cari] [Status] [Muat ulang]                      │
│ ──────────────── │ ┌───────────────────────────────────────────────┐ │
│ Menu berikutnya  │ │ Tabel DataTables / empty / error / loading     │ │
│ (bukan tautan    │ │ detail tindakan per baris                     │ │
│ aktif palsu)     │ └───────────────────────────────────────────────┘ │
└──────────────────┴───────────────────────────────────────────────────┘
```

## Komponen yang harus distandardkan

1. `AdminShell`: sidebar responsif, header minimal, halaman penuh. Navigasi keyboard dan fokus jelas.
2. `AdminPageHeader`: judul *task-oriented*, 1 kalimat penjelas, 1 tindakan utama saja jika relevan.
3. `AdminToolbar`: search, filter status/tanggal jika benar-benar didukung backend, reset/loading.
4. `DataTableFrame`: state loading/error/no-results, header dan pagination ringkas, tindakan per baris.
5. `ConfirmAction`: modal untuk approve/reject/suspend yang menuntut alasan sesuai bisnis; hasil ditampilkan via toast, kesalahan input tetap inline.
6. `Status`: lencana kecil, membedakan status tanpa bergantung pada warna semata.

## Pengujian persetujuan sebelum coding

- Preview minimal **desktop 1440px dan mobile 390px**: Ringkasan serta Registrasi peserta (terisi, kosong, dan loading/error).
- Bandingkan dengan kepadatan/tata informasi INA-GOV, tetapi nilai dengan brand & design system Siapegawai.
- Jangan menambah menu bank soal / kelas / paket sebagai navigasi aktif sampai routenya ada.
- Verifikasi CSP, output escaped, permission admin, aksesibilitas, dan DataTables satu route seperti implementation plan.

**Prioritas sesudah hotfix navigasi publik:** setujui wireframe admin, lalu terapkan shell admin sebagai patch terpisah. Halaman login dipertahankan sementara sesuai keputusan pemilik.
