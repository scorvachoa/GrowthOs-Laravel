@echo off
setlocal
cd /d "%~dp0"

rem ============================================================
rem  SUBIR BD: importa una base de datos local a la nube
rem
rem   Uso:
rem     subir_bd.bat                 vuelca la BD local de Laragon y la sube
rem     subir_bd.bat archivo.sql     sube un .sql existente
rem     subir_bd.bat --yes [archivo] omite la confirmacion (para automatizar)
rem
rem  ATENCION: REEMPLAZA todos los datos de la BD en la nube.
rem ============================================================

set "SSH_KEY=E:\ssh-key-2026-10-10.key"
set "HOST=ubuntu@146.181.38.145"
set "MYSQLDUMP=C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe"
set "CLEANUP="
set "SKIP="

echo ============================================
echo  SUBIR BD  local -^> nube
echo ============================================
echo.

if "%~1"=="--yes" (
    set "SKIP=1"
    shift
)
if "%~1"=="--yes" shift

if "%~1"=="" goto :volcar_local
set "SQL=%~1"
if not exist "%SQL%" goto :no_existe
goto :confirmar

:volcar_local
echo [1/3] Volcando base de datos local (Laragon)...
set "SQL=%TEMP%\growthos_subir_%RANDOM%.sql"
set "CLEANUP=%SQL%"
"%MYSQLDUMP%" -uroot --single-transaction --routines --triggers growthos > "%SQL%"
if errorlevel 1 goto :error_dump
for %%A in ("%SQL%") do if %%~zA LSS 1000 goto :error_dump
goto :confirmar

:no_existe
echo ERROR: no existe el archivo "%SQL%"
exit /b 1

:confirmar
echo Archivo: %SQL%
echo.
echo ATENCION: esto REEMPLAZA todos los datos de la BD
echo           "growthos" del servidor %HOST%
echo.
if defined SKIP goto :importar
choice /C SN /N /M "Continuar? [S]i / [N]o "
if errorlevel 2 goto :cancelado

:importar
echo.
echo [2/3] Subiendo e importando en la nube...
type "%SQL%" | ssh -i "%SSH_KEY%" %HOST% "sudo mysql growthos"
if errorlevel 1 goto :error_import

echo [3/3] Actualizando esquema y permisos en el servidor...
ssh -i "%SSH_KEY%" %HOST% "cd /var/www/growthos && sudo -u www-data php artisan migrate --force && sudo -u www-data php artisan permission:cache-reset && curl -s -o /dev/null -w 'HTTP %%{http_code}\n' http://localhost/"
if errorlevel 1 goto :error_import

if defined CLEANUP del "%CLEANUP%" >nul 2>&1
echo.
echo ============================================
echo  BD SUBIDA OK  -  http://146.181.38.145/
echo ============================================
exit /b 0

:cancelado
if defined CLEANUP del "%CLEANUP%" >nul 2>&1
echo Cancelado.
exit /b 0

:error_dump
if defined CLEANUP del "%CLEANUP%" >nul 2>&1
echo.
echo ERROR: no se pudo volcar la BD local.
echo        Revisa que MySQL de Laragon este activo.
exit /b 1

:error_import
if defined CLEANUP del "%CLEANUP%" >nul 2>&1
echo.
echo ERROR: fallo la importacion. Revisa los mensajes de arriba.
exit /b 1
