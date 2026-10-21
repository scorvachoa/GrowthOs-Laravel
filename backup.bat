@echo off
setlocal
set "SSH_KEY=E:\ssh-key-2026-10-10.key"
set "HOST=ubuntu@146.181.38.145"
set "BACKUPS=E:\growthos_backups"

rem ============================================================
rem  BACKUP: descarga desde la nube a local
rem   - growthos_<fecha>.sql   (base de datos completa)
rem   - storage_<fecha>.tar.gz (logos e informes subidos)
rem ============================================================

echo ============================================
echo  BACKUP  nube -^> local
echo ============================================

if not exist "%BACKUPS%" mkdir "%BACKUPS%"
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmm"') do set "TS=%%i"
set "SQL=%BACKUPS%\growthos_%TS%.sql"
set "TAR=%BACKUPS%\storage_%TS%.tar.gz"

echo.
echo [1/2] Descargando base de datos...
ssh -i "%SSH_KEY%" %HOST% "sudo mysqldump --single-transaction --routines --triggers growthos" > "%SQL%"
if errorlevel 1 (
    del "%SQL%" >nul 2>&1
    goto :error
)
for %%A in ("%SQL%") do if %%~zA LSS 1000 (
    echo       ERROR: el archivo de la base de datos quedo vacio.
    del "%SQL%" >nul 2>&1
    goto :error
)

echo [2/2] Descargando archivos subidos (logos e informes)...
ssh -i "%SSH_KEY%" %HOST% "cd /var/www/growthos/storage && tar czf - app 2>/dev/null" > "%TAR%"
if errorlevel 1 (
    del "%TAR%" >nul 2>&1
    goto :error
)

echo.
echo Copias guardadas en %BACKUPS%:
dir /b "%SQL%" "%TAR%"
echo.
echo Listo.
exit /b 0

:error
echo.
echo ERROR: la copia fallo. Revisa tu conexion y la clave SSH.
exit /b 1
