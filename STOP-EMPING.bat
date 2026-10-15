@echo off
title Emping - Stop Server
cd /d D:\xampp
echo Menghentikan Apache ^& MySQL...
call "D:\xampp\xampp_stop.exe"
echo Selesai.
pause
