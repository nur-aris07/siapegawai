@echo off
setlocal
cd /d "%~dp0\..\.."
set "PHP_BIN=E:\Development\Runtime\PHP\8.3\php.exe"
if not exist "%PHP_BIN%" goto php_missing

"%PHP_BIN%" siapegawai-update.php --dry-run || goto failed
"%PHP_BIN%" siapegawai-update.php --apply || goto failed
"%PHP_BIN%" siapegawai-update.php --verify || goto failed
"%PHP_BIN%" artisan optimize:clear || goto failed
rem Check database, SMTP, and the real dump binary BEFORE lengthy test/build.
"%PHP_BIN%" tools\notify-email\activate.php --check || goto failed
"%PHP_BIN%" artisan test tests\Feature\SiapegawaiAccountEmailTest.php || goto failed
"%PHP_BIN%" artisan test || goto failed
call npm run build || goto failed
git diff --check || goto failed
"%PHP_BIN%" tools\notify-email\activate.php --check || goto failed
"%PHP_BIN%" tools\notify-email\activate.php --activate --db=siapegawai || goto failed
start "Siapegawai Email Worker" /MIN "%~dp0worker.cmd"
powershell -NoProfile -NonInteractive -Command "Start-Sleep -Seconds 4" || goto failed
"%PHP_BIN%" tools\notify-email\activate.php --status --require-worker || goto failed
git status --short --branch

echo [PASS] NOTIFY-EMAIL-01 C-07 UAT: source, QA, migration, config, and local worker active.
echo [NEXT] Buka /admin/email dan daftarkan peserta UAT baru untuk membuktikan pending-^>sent.
exit /b 0
:php_missing
echo [STOP] PHP 8.3 tidak ditemukan pada lokasi yang ditentukan.
exit /b 2
:failed
echo [STOP] Proses berhenti. Jangan lanjutkan secara paksa. Periksa output di atas dan kirim error.
exit /b 2
