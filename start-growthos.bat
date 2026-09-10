@echo off
title GrowthOS - Starting...

cd /d C:\laragon\www\growthos

echo Iniciando GrowthOS...

:: Iniciar PHP server
start "GrowthOS Server" /min cmd /c "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan serve"

:: Esperar a que arranque
timeout /t 2 /nobreak >nul

:: Iniciar Vite dev
start "GrowthOS Vite" /min cmd /c "npm run dev"

:: Esperar a que Vite arranque
timeout /t 4 /nobreak >nul

:: Abrir navegador
start http://localhost:8000

echo GrowthOS iniciado correctamente!
echo Presiona cualquier tecla para cerrar esta ventana...
pause >nul
