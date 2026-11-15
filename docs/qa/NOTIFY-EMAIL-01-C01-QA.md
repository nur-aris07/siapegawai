# NOTIFY-EMAIL-01 / C-01 — SMTP, outbox, template, QA

Baseline Git: `a1ea5530cf1439c4534f7633a4f14771bca9cc76`. Owner approved scope B REV-01 **plus SMTP setup and real mailbox UAT**.

## Safety and configuration

- Installer only writes guarded PHP/Blade/test/docs; DOES NOT run Composer, migration, SQL, Git or change `.env`.
- BACK UP ACTIVE DATABASE before migrating. Migration `2026_11_15_000019_create_account_email_outbox.php` must be executed by operator separately after checking actual database target.
- Config remains `SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=false` by default; SMTP sending test is a separate explicit superadmin action.
- Put credentials only in `.env` (never commit, never send them to chat):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.provider-yang-benar
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=alamat-akun-smtp
MAIL_PASSWORD=APP_PASSWORD_ATAU_SECRET_PROVIDER
MAIL_FROM_ADDRESS=alamat-pengirim-yang-sudah-diotorisasi
MAIL_FROM_NAME="Siapegawai"
QUEUE_CONNECTION=database
SIAPEGAWAI_SMTP_TEST_RECIPIENT=mailbox-uat-yang-kamu-kuasai
SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=false
```

**SMTP provider varies:** Laravel 13 uses `MAIL_SCHEME=smtp` (port 587 with STARTTLS) or `smtps` (port 465) according to provider; follow *actual provider docs*, not fake values. Never use fallback `MAIL_MAILER=log`, `array` or `failover` to claim real delivery. Use authorized sender and SPF/DKIM/DMARC checks before production.

## Browser UAT

1. `php artisan optimize:clear`; login as **superadmin**; open `/admin/email`.
2. Check table migration status, SMTP readiness, and disabled transactional flag.
3. Open HTML previews from the browser; inspect mobile width, Indonesian copy, link URL (APP_URL), and sender identity.
4. Configure real SMTP and recipient only in `.env`; click **Kirim satu email uji**. If UI says SMTP accepted, check controlled mailbox Inbox and Spam plus source headers; 'accepted' is NOT mailbox received.
5. Enter deliberately wrong SMTP password in local UAT only; verify UI shows failure without revealing credentials; restore original safely.
6. Once worker + backups + legal approved, owner may set `SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=true` and `QUEUE_CONNECTION=database`, then `php artisan optimize:clear`.
7. Start isolated worker **in separate CMD**: `php artisan queue:work database --queue=account-emails --tries=3 --timeout=25 --sleep=3 --stop-when-empty`. For continuous operation, use managed service, NOT a development tab.
8. New FREE email registration, Google signup, completed invitation, and historical decisions create unique account intents. Password broker invite/reset remains unchanged.
9. Inspect pending/sent/failed in `/admin/email`; no status account modifications from SMTP errors.

## Failure and semantics

- `sent` means SMTP transport accepted, not inbox read, delivery guaranteed, or recipient ownership verified.
- Retries may duplicate transport acceptance when network timeouts are ambiguous. Exhausted or processing-stuck records require operator review rather than blind resend.
- SMTP secrets and recipient lists are not shown in admin page. Audit records contain result categories only.
- Email UAT through database `siapegawai` constitutes live changes. Isolate test recipients and ensure backups before any new data / migration.
- Commands `artisan test` must resolve testing DB `siapegawai_testing`, never active `siapegawai`. PHP 8.3 expected.
- Full QA acceptance requires focused tests, entire test suite, Vite, browser previews, real email receipt, failure path, and admin authorization.


## C-02 — Deliverability / spam-folder education / bounded manual resend

- SMTP accepted is NOT delivery to Inbox, not authentication proof, and not mailbox ownership proof. UAT mail reached Spam: investigate domain and reputation before repeated sends.
- For sender-owned domains, configure SPF/DKIM/DMARC at DNS/provider; verify From alignment, TLS, PTR where applicable, and reputational signals. Gmail personal domains cannot be DNS-managed by the application operator.
- Admin email screen gives diagnostik and Inbox/Spam/Promosi guidance before resend. SMTP test acceptance explicitly warns that mailbox placement is unknown.
- Free registration success gives a clear note: account is active even when optional email is disabled, queued, delayed or filtered.
- Existing pending/never-attempted email action remains 'Antrekan'; manual resend is ONLY for originally SMTP-accepted account-status notification, NOT broker password reset / admin invitation, and not from participant UI.
- Manual resend requires active superadmin, CSRF, server-side mailbox checked confirmation, SMTP + outbox readiness, recipient/role check, original event, 30-minute cooldown, max 2 per original, and an audit entry.
- Resend creates a NEW outbox intent and dispatches only after DB commit; no modification of past SMTP accepted state. Pending/failed/ambiguous resend never automatically repeated by UI. Worker handles it with existing bounded attempts.
- `SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=false` must remain OFF until verified migration, queue worker and backed-up DB. No SQL/migration/Git/.env writes from installer.
- UAT: (1) inspect Gmail Show original SPF/DKIM/DMARC; (2) see spam instructions; (3) verify SMTP test actual mailbox; (4) verify resend early rejected and one after cooldown queued; (5) verify no unauthorized/duplicate/more than 2 resends; (6) test full Laravel and Vite.


## C-03 — Aktivasi lengkap lokal: backup → migration → flag → worker

**Owner menginstruksikan tidak menunda aktivasi sampai milestone lain.** Semua langkah disiapkan dalam installer C-03 beserta operator yang bisa dieksekusi langsung. Tidak menjalankan migrasi secara diam-diam dalam `--apply`: operator menjalankan `--activate --db=siapegawai` secara eksplisit dan database diverifikasi sebelum perubahan.

- Hanya local UAT (`APP_ENV=local`, `APP_URL=http://127.0.0.1:8000` atau `http://localhost:8000`, database tepat `siapegawai`). Kondisi salah *fail closed*.
- `tools/notify-email/activate.php --check` menguji kesiapan SMTP, driver antrean, tabel jobs/failed_jobs, dan versi migrasi tanpa menampilkan secret.
- `--activate --db=siapegawai` membackup **seluruh** database InnoDB dengan `mariadb-dump`/`mysqldump` sebelum migrasi outbox. File SQL privat di `.siapegawai-backup` dan SHA256 disimpan; jika binary tidak ditemukan, set `SIAPEGAWAI_DB_DUMP_BINARY` pada `.env` ke path dump yang valid. Proses tidak boleh melewati backup bila gagal.
- Migration dijalankan hanya `2026_11_15_000019_create_account_email_outbox.php`, tidak `migrate:fresh`, tidak memodifikasi CAT/Kelas/QQ atau tabel pengguna.
- Setelah migrasi sukses, script membuat backup `.env` privat dan mengubah `SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED=true`, lalu menghapus cache config.
- Jalankan worker lokal `tools\notify-email\worker.cmd` di konsol lain; menggunakan PHP 8.3 sesuai runtime owner, melayani **hanya antrean `account-emails`**. Wrapper mengunci agar tidak ada dua worker sejenis dan menulis heartbeat berkala ke folder privat untuk panel `/admin/email`. Restart worker otomatis saat proses Laravel selesai; worker lokal tidak hidup setelah reboot atau konsol ditutup.
- `--status` dan panel admin memperlihatkan worker heartbeat secara jujur. Status `sent` berarti **SMTP menerima**, bukan bukti masuk Inbox.
- Tes email registrasi baru gratis / Google / undangan; periksa outbox `pending→sent`, mailbox UAT; uji kegagalan worker (pending tetap), manual resend terbatas dan otorisasi; pastikan SMTP password tidak pernah dicetak.
- **Production gate tetap OPEN**: uji restore backup, legal, validitas domain SPF/DKIM/DMARC, worker process manager permanen, monitoring, CSP dan rollback harus disahkan sebelum live.

- Satu perintah Windows CMD tersedia: `call tools\notify-email\complete-uat.cmd` (mengambil file PHP dari runtime owner, QA, backup, migrasi, flag, worker dan status). Operator tetap dapat menjalankan langkah-langkah terpisah untuk diagnosis.
- `--activate` selalu mengambil snapshot database sebelum migrasi **atau** perubahan flag dari OFF ke ON; ulang pada keadaan flag ON/outbox siap hanya melakukan no-op.


## C-04 — Koreksi QA dan root aktivasi (temuan hasil C-03 owner)

- C-03 berhasil memasang file dan lolos verifikasi **22/22**, tetapi focused test gagal karena halaman admin menampilkan status **Belum terdeteksi / berhenti** ketika heartbeat belum ada. Test keliru mewajibkan literal `heartbeat` yang hanya tampil dalam status terdeteksi. C-04 menguji informasi status yang selalu tersedia tanpa mengharuskan worker sudah berjalan sebelum aktivasi.
- C-03 juga menemukan regresi lokasi proyek: `tools/notify-email/activate.php` dan `worker.php` memakai `dirname(__DIR__, 3)`, sehingga keluar dari root Laravel; C-04 memakai `dirname(__DIR__, 2)` pada kedua script dan `proc_open` menggunakan `$root` yang telah dihitung. Ditambahkan test guard source path.
- Jalankan `complete-uat.cmd` **setelah source C-04 di-apply dan verify**. Ia akan melakukan PHPUnit, build, preflight, backup penuh MariaDB/MySQL, migration satu tabel outbox, mengaktifkan flag email lokal, memulai worker dan memeriksa heartbeat. Tidak ada perubahan database dalam installer.
- Jika binary `mariadb-dump`/`mysqldump` tidak ada atau backup tidak valid, script harus berhenti sebelum migrasi. Periksa folder backup privat dan status; jangan memaksa migrasi.
- Tidak menyatakan email benar-benar diterima Inbox hanya dari status antrean / SMTP accepted. Lakukan registrasi dummy melalui browser setelah worker aktif dan periksa catatan outbox serta mailbox UAT.
- **Sebelum C-04, script C-03 berhenti di test; backup, migration, flag dan worker belum dijalankan oleh satu-rangkaian tersebut**. Jangan klaim auto-email ON sampai status sesudah aktivasi benar-benar diperoleh.


## C-05 — Pencarian otomatis program backup MariaDB (hasil owner C-04)

- C-04 berhasil memasang patch **22/22**, fokus email **9 PASS / 56 assertions**, full PHPUnit **227 PASS / 2.040 assertions**, Vite PASS. Aktivasi **berhenti di preflight backup** karena `mariadb-dump`/`mysqldump` tidak tersedia di PATH. Pada tahap itu **DB, migration, flag, worker belum diubah**.
- C-05 memperluas pencarian program backup melalui folder **MariaDB Native di `E:/Development/Runtime/MariaDB`** serta subfolder `bin`, versi, dan lokasi Program Files yang umum. Tidak melakukan pencarian seluruh disk atau memasang tool otomatis. PATH tetap prioritas, lalu lokasi terdeteksi.
- Jika `SIAPEGAWAI_DB_DUMP_BINARY` ada di `.env`, lokasi manual tetap diprioritaskan. Jika binary tetap tidak ditemukan, proses **fail-closed sebelum migration** dengan panduan. Tidak mengabaikan backup; ukuran, struktur users, SHA-256, dan InnoDB tetap diperiksa sesuai C-04.
- Perubahan C-05 hanya: `tools/notify-email/activate.php`, test, dan dokumentasi QA. Tidak menyentuh logika registrasi, email, tabel, aturan entitlement atau kredensial.
- Setelah menjalankan installer C-05 dry-run → apply → verify, jalankan `complete-uat.cmd` kembali. Pastikan log menunjukkan backup SQL disimpan, migration outbox berhasil, feature flag ON, worker heartbeat. Uji satu registrasi dummy dan periksa status outbox → SMTP accepted serta Inbox/Spam.
- Jika utilitas dump tidak terpasang sama sekali, operator perlu memasang **client MariaDB/MySQL yang sesuai** atau mengatur path binary resmi; jangan mem-bypass backup. Restore terpisah tetap bagian gate rilis P0.


## C-06 — Root cause C-05 dan preflight berbasis instalasi MariaDB asli

- Hasil owner C-05: installer **22/22**, email feature **10 PASS / 65 assertions**, full PHPUnit **228 PASS / 2.049 assertions**, dan Vite PASS; **tetapi backup tidak dimulai** karena `mariadb-dump/mysqldump` gagal ditemukan melalui lokasi yang diperkirakan. Migration, flag ON, worker belum dimulai. **Jangan klaim selesai.**
- Pencarian C-05 memakai tebakan PATH/runtime; tidak memperoleh direktori server aktif. C-06 membaca `@@GLOBAL.basedir` MariaDB melalui PDO/Laravel sebelum mencari backup executable di jalur instalasi server yang sebenarnya, lalu menguji program dengan `--version`.
- Jika executable tidak terpasang atau PHP tidak dapat menjalankannya, **preflight berhenti lebih awal, sebelum PHPUnit/Vite**, dengan informasi lokasi server (`basedir`) dan petunjuk operator `SIAPEGAWAI_DB_DUMP_BINARY` atau instalasi MariaDB command-line client. Tidak mengasumsikan semua instalasi MariaDB server menyertakan utilitas dump.
- Fixed `backupDatabase()` sebelumnya mengoper variabel `$root` yang bukan variabel lokal ke `proc_open`; kini memakai `$GLOBALS['root']` yang sudah dihitung. Unit regression menjaga path proses.
- **Tidak mengubah dan tidak melewati** backup SQL, validasi hash, struktur tabel, InnoDB, migrasi target `siapegawai`, aktivasi flag lokal, maupun worker.
- C-06 memperbarui `tools/notify-email/activate.php`, `tools/notify-email/complete-uat.cmd`, test email, dan dokumentasi ini. Status berikutnya harus diperoleh dari log nyata setelah `complete-uat.cmd`.

## C-07 — Koreksi test yang tertinggal sesudah perbaikan C-06

- Log owner C-06 membuktikan **preflight menemukan `mysqldump.exe` dan berhasil mengeksekusi `--version`**, dengan `SIAPEGAWAI_DB_DUMP_BINARY` diset pada komputer owner. Tidak perlu mengubah `.env` untuk kendala yang dilaporkan.
- QA berhenti karena assertion `assertStringContainsString('/Development/Runtime/MariaDB', $activation)` mengharuskan sebuah lokasi hardcoded, sementara C-06 membangun root MariaDB dari variabel server dan nama folder. Assertion itu keliru.
- C-07 memeriksa aliran `serverInstallRoots() -> findDumpCandidates(...)` dan penggunaan `$serverRoots` di fungsi pencarian. **Tidak mengubah kode aktivasi, utilitas dump, migrasi, flag, SMTP, atau pekerja**.
- Label hasil skrip CMD diganti menjadi C-07 supaya output jelas.
- Backup, migration, pengaktifan flag, dan heartbeat worker **belum terbukti terlaksana pada komputer owner** sampai rangkaian UAT C-07 selesai. Jangan mengklaim fitur otomatis aktif dari hasil preflight saja.
