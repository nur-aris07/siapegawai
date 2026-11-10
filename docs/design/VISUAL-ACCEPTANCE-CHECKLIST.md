# Siapegawai — Visual, UX, Security Acceptance Checklist

**Versi 1.0 / Review draft / 8 November 2026**  
**Referensi:** `DESIGN-SYSTEM-AND-VISUAL-CONTRACT.md`, prototipe v4 dan screenshot enam halaman, `IMPLEMENTATION-PLAN-v2.2.md`.  
**Aturan:** Gunakan untuk PR frontend. `php artisan test` PASS dan `npm run build` PASS **tidak menggantikan** visual acceptance.

## A. Bukti yang wajib dilampirkan ke PR

- [ ] Nama halaman/route dan jenis layout: **Public / Account / Participant Workspace / Admin Workspace**.
- [ ] Klasifikasi: **Preserved** (referensi v4) / **Adapted** (alasan/approval) / **New** (mockup/approval).
- [ ] Screenshot *before* (v4 bila ada) vs *after* (Laravel) **dengan viewport yang sama**.
- [ ] Perubahan copy/konten dinyatakan, dan berbeda bila perlu karena data nyata — bukan karena desain ulang tanpa izin.
- [ ] Bukti desktop 1366 atau 1440px dan mobile 375px; tes overflow juga pada **320, 768, 1024px**.
- [ ] Bukti interaksi keyboard dan state penting.
- [ ] `php artisan test`, `npm run build`, dan pengecekan CSP/report browser dicatat.

## B. Baseline enam halaman v4

### Beranda

- [ ] Logo header **`logo-accent.svg`** dengan Plus Jakarta Sans; utility bar, nav dan underline aktif sebagaimana v4.
- [ ] Hero cream dengan pembagian 2 kolom, copy/CTA/3 manfaat; figur `group-khaki.webp` + label atas, `01.`, pita navy bawah.
- [ ] Urutan **manifesto 4 kolom → tiga langkah → preview soal → jalur SKD/SKB → segmen karakter navy → CTA penutup → footer**.
- [ ] Tidak ada gradient, testimonial fiktif, badge, card berulang atau angka prestasi rekaan.

### Demo CAT

- [ ] Intro 9 soal / 12 menit / TWK-TIU-TKP / penilaian internal.
- [ ] **Saat mengerjakan**: heading ringkas, timer kanan, bukan hero 500px atau latar krem besar.
- [ ] Panel soal dan sidebar nomor soal terpisah; toolbar kategori + counter.
- [ ] Radio native + pilihan A/B/C/D dalam kotak; state selected/hover/focus jelas.
- [ ] Navigasi Sebelumnya/Berikutnya **berdampingan** di footer panel; tandai untuk ditinjau.
- [ ] Nomor 1–9 current/answered/flagged + legend, submit dengan dialog konfirmasi.
- [ ] Hasil valid saat submit/timeout, pembahasan ditampilkan setelah waktu yang tepat.

### Bank Soal

- [ ] Heading task-oriented “Bank soal & pembahasan”, breadcrumb dan deskripsi ringkas.
- [ ] Filter pills `Semua, TWK, TIU, TKP, SKB` + search kanan (bukan dropdown + tombol filter tanpa approval).
- [ ] List soal ber-divider, label dan kode kecil, opsi 2 kolom, disclosure kunci/pembahasan.
- [ ] Count nyata, filter dan reset bekerja, empty/error/loading state tidak menipu.

### Kelas

- [ ] Katalog status publik `planned/open/closed` jujur; tidak menjanjikan tutor/jadwal yang belum ada.
- [ ] Baris SKD/SKB + silabus list dan divider, bukan grid card seragam.
- [ ] Konten privat berizin hanya pada `/kelas-saya*`, tidak disajikan di katalog.

### Paket

- [ ] Paket gratis yang tersedia diprioritaskan; paket konsep masih status perencanaan.
- [ ] Tidak ada checkout/pembayaran instan/invoice, klaim manfaat atau harga rekaan.
- [ ] Layout baris editorial/divider; bukan pricing 3-card template.

### Progres

- [ ] Metrik dihubungkan garis, bukan card dekoratif generik.
- [ ] Histori berupa baris/tautan pembahasan dan diambil dari **database milik user** (bukan demo localStorage) pada Laravel.
- [ ] Riwayat tak bisa dihapus sembarang bila harus dipertahankan; state kosong ditangani.

## C. Pemisahan empat shell

- [ ] Guest/public route memakai `layouts/public` walau browser punya sesi login (konteks route tetap publik).
- [ ] Login, daftar, reset, pending memakai `layouts/account` tanpa footer/nav marketing penuh.
- [ ] Peserta aktif punya `layouts/workspace` tersendiri untuk tryout/progres/kelas-saya.
- [ ] Superadmin login diarahkan ke workspace admin dan **tidak** mendapat header/footer public.
- [ ] Katalog kelas/paket/bank contoh & demo tetap publik; tryout anggota, enrollment, profil dan progres server tetap private.
- [ ] Route/middleware/policy/resource ownership diuji; layout visual **bukan** mekanisme izin.

## D. Toast notification

- [ ] Semua toast melalui adapter `notify` (tidak memanggil Toastr acak dari tiap Blade).
- [ ] Success/info/warning/error memiliki visual konsisten: Plus Jakarta Sans, border ringan, radius kecil, icon/teks, contrast mencukupi.
- [ ] Pesan aman sebagai **text**, tidak menyisipkan HTML hasil input user/response arbitrer.
- [ ] **Jika Toastr klasik:** `escapeHtml: true` disetel; uji payload HTML dalam pesan/title, review laporan XSS dan jQuery.
- [ ] Tidak menutup tombol atau form pada mobile; tidak mencuri fokus keyboard; tidak duplicate tak terbatas.
- [ ] Validasi tetap inline, aksi destruktif tetap membutuhkan konfirmasi. Error kritis yang perlu tindakan tidak hilang begitu saja.
- [ ] Jika Toastr gagal CSP atau audit keamanan, library diganti lewat keputusan review, bukan melonggarkan CSP tanpa alasan.

## E. CSP dan keamanan frontend

- [ ] CSS/JS custom masuk `resources/css/**` dan `resources/js/**` via Vite; font/icon/brand self-hosted.
- [ ] Tidak ada handler `onclick`, `<script>` inline, `<style>` inline, `javascript:` link, `eval`, `new Function` atau style attr arbitrer.
- [ ] Nonce Vite dipakai bila diperlukan; CSP disusun per request sesuai environment.
- [ ] Semua route diaudit di `Content-Security-Policy-Report-Only` sebelum enforce; HMR development dipisah dari policy produksi.
- [ ] Tidak menambahkan `unsafe-inline`/`unsafe-eval` hanya untuk menghilangkan warning browser.
- [ ] Render Blade escaped, toast text aman, DOM untrusted input memakai `textContent`, tidak raw HTML.
- [ ] CSRF, validation, policy, rate limit, no secret di URL/log/DOM, private file access berlaku pada UI terbaru.

## F. Rubrik anti-generik (kualitas, **bukan AI detector ilmiah**)

- [ ] Layout/spacing/hierarki dipilih untuk pekerjaan **belajar CPNS**, bukan layout SaaS default.
- [ ] Tidak memakai identitas brand palsu, gradient/glow/glass/card berlebihan.
- [ ] Copy dan CTA spesifik dan dapat dipertanggungjawabkan dengan fitur yang ada.
- [ ] Data dashboard/angka/status berasal dari sistem, bukan dummy yang terlihat realistis.
- [ ] State empty/error/loading/permission/disabled/success lengkap.
- [ ] Semantik HTML, aria label, focus order, zoom, kontras, dan mobile edge-case diperiksa.
- [ ] Komponen reusable tetapi tidak menyamakan visual halaman dengan tujuan yang berlainan.

## G. Hasil persetujuan

```text
Route / fitur:
Layout:
Status: Preserved / Adapted / New
Referensi screenshot (jika ada):
Perbedaan disengaja dan alasan:
Functional tests: PASS / FAIL / Belum diuji
Visual desktop/mobile: PASS / FAIL / Belum diuji
Accessibility: PASS / FAIL / Belum diuji
CSP/JS/CSS: PASS / FAIL / Belum diuji
Toast & DOM sanitization: PASS / FAIL / N/A
Reviewer dan keputusan:
```

**Merge gate:** P0 visual, akses, dan CSP harus PASS. Jika ada perubahan *Adapted/New*, bukti persetujuan desain harus tercatat.
