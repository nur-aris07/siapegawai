# Quiz Kilat — ADR v1.2: in-place feedback dan durasi 5 detik

**Keputusan produk terbaru:** 12 November 2026. Menggantikan ketentuan **durasi feedback tiga detik** dan alur visual lama pada `QQ-SPEC-ADR-v1.1.md`. Semua keputusan v1.1 lainnya tetap berlaku.

1. **AJAX tanpa full-page reload:** Pemilihan jawaban, penguncian, feedback, dan penerbitan soal berikutnya memakai request latar belakang. Hanya area kartu soal yang diperbarui; navigasi penuh boleh dilakukan ketika quiz selesai menuju halaman hasil. Tanpa JavaScript, POST/redirect Blade tetap aman sebagai fallback.
2. **Pilihan tegas:** pilihan aktif berlatar biru muda dengan border kontras; setelah server mengunci, jawaban benar berwarna hijau (`--sage/#E8EFE8`, `--green/#2F765A`), pilihan peserta yang salah merah (`--red/#B44B42` pada border dan surface merah muda). Teks status dan simbol tetap wajib karena warna bukan satu-satunya indikator.
3. **Feedback pada kartu soal yang sama:** pertanyaan dan semua pilihan tetap terlihat, dua status warna dibedakan, kunci dan pembahasan asli versi terbit ditampilkan tepat di bawah pilihan dengan aksen gold tenang. Sebelum terkunci, kunci dan pembahasan tidak boleh dikirim.
4. **Feedback minimal 5 detik:** `feedback_until` disimpan server selama 5 detik sejak soal dikunci. Browser juga mempertahankan feedback terlihat minimal 5 detik setelah ditampilkan, termasuk ketika memulihkan sesi. Hidden tab tidak boleh mempercepat fase terlihat. Soal berikutnya dibuat hanya setelah request `next` sukses dan gate server terpenuhi.
5. **Tetap aman saat retry:** answer dan next tetap idempotent/terotorisasi; jika jaringan gagal atau respons hilang, ambil state terbaru dari server melalui endpoint sesi yang sama. Tidak ada debit kuota tambahan, dan timer soal yang belum dikeluarkan tidak boleh berjalan.

**Kontrak visual:** `docs/design/DESIGN-SYSTEM-AND-VISUAL-CONTRACT.md` — v4 color tokens, `Plus Jakarta Sans`, task-specific participant workspace, dan CSS yang dibatasi pada Quiz Kilat. CAT, kelas, bank soal, dan desain halaman publik tidak diubah.
