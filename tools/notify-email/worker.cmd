@echo off
setlocal
cd /d "%~dp0\..\.."
set "PHP_BIN=E:\Development\Runtime\PHP\8.3\php.exe"
if not exist "%PHP_BIN%" (
  echo [STOP] PHP 8.3 not found. Adjust PHP_BIN in this local worker.cmd.
  exit /b 2
)
"%PHP_BIN%" tools\notify-email\worker.php
