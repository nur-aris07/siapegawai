# QQ-CORE-02 — Participant AJAX UX refinement v1.12

Baseline Git: `e99ef4d7c4f63194ffd0caa1ae7df4cd2fdc36bd`. Lingkup hanya UI peserta Quiz Kilat, controller output state, service feedback 5 detik, dan feature test; tidak ada migrasi atau perubahan kuota.

## Kontrak runtime

- GET `/quiz-kilat/sesi/{attempt}` mengirim Blade lengkap secara normal, atau JSON state jika `Accept: application/json` (same URL, same ownership).
- POST `/quiz-kilat/sesi/{attempt}/jawab` dan `/lanjut` tetap redirect saat non-JS; jika `Accept: application/json`, mengembalikan `phase`, indeks, metadata durasi dan **fragment HTML ter-escape dari Blade**.
- Browser memakai `fetch()` dan `DOMParser` untuk mengganti `#qq-exam` saja. Tidak memanggil `window.location.assign()` di antara soal; navigasi penuh hanya setelah selesai ke hasil.
- Saat feedback, opsi tidak hilang: hijau untuk kunci benar, merah untuk jawaban yang salah, pembahasan dalam satu panel di bawah pilihan. Kunci dan pembahasan tidak ada pada state `question`.
- Feedback min. lima detik sejak server lock, dengan visibilitas minimum lima detik pada klien; server tetap berwenang atas `next`.
- CSRF, ownership dan throttle tetap ada; masalah jaringan diikuti GET state untuk menghindari pengiriman ganda tanpa sengaja.

## QA yang wajib di Windows

1. `php artisan test --filter=SiapegawaiQuickQuizAttemptTest` lalu `php artisan test` — uji JSON, escrow feedback 5 detik, HTML fallback, no leak.
2. `node --check resources/js/member-quick-quizzes.js` lalu `npm run build`.
3. Browser desktop + mobile 360/390/768/1440: pilih jawaban, pilih salah, pilih benar, timeout, retry jaringan lambat, tab background, reload feedback. Pastikan kartu sama dan tidak ada document-level navigation pada jawaban/lanjut (Network tab: Fetch/XHR), warna serta pembahasan terlihat.
4. Pastikan kuota berubah tepat sekali saat start saja, bukan saat answer/next/resume. Tidak ada angka kuota default baru.

**Acceptance belum dianggap lulus** hingga seluruh QA lokal dan visual terdokumentasi. V1.11.2 concurrency evidence tetap relevan untuk service kuota yang tidak berubah.
