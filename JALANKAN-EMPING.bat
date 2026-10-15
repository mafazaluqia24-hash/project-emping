@echo off
title Emping - Jalankan Aplikasi
cd /d D:\xampp
echo [1/3] Menjalankan Apache ^& MySQL...
start "" "D:\xampp\xampp_start.exe"
echo [2/3] Menunggu 7 detik agar Apache ^& MySQL siap...
timeout /t 7 /nobreak >nul
echo [3/3] Membuka http://localhost/emping ...
start "" http://localhost/emping
echo.
echo Selesai. Biarkan Apache/MySQL tetap jalan.
echo Untuk berhenti: klik STOP-EMPING.bat
pause
