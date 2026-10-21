@echo off
setlocal
cd /d "%~dp0"

rem ============================================================
rem  DEPLOY: actualiza GrowthOS en la nube a partir de local
rem  Uso:  deploy.bat "mensaje de commit"
rem  (si no pasas mensaje, usa uno generico con fecha)
rem ============================================================

set "SSH_KEY=E:\ssh-key-2026-10-10.key"
set "HOST=ubuntu@146.181.38.145"
set "APP=/var/www/growthos"
set "URL=http://146.181.38.145/"

if "%~1"=="" (set "MSG=Actualizacion automatica %date% %time%") else set "MSG=%~1"

echo ============================================
echo  DEPLOY  local -^> nube
echo ============================================

echo.
echo [1/3] Subiendo cambios a GitHub...
git add -A
git diff --cached --quiet
if %errorlevel%==0 (
    echo       Sin cambios nuevos para commitear.
) else (
    git commit -m "%MSG%"
    if errorlevel 1 goto :error_git
)
git push origin main
if errorlevel 1 goto :error_git

echo.
echo [2/3] Actualizando servidor (git pull, dependencias, build, migraciones)...
ssh -i "%SSH_KEY%" %HOST% "cd %APP% && sudo -u www-data env HOME=/tmp bash -c 'cd %APP% && git pull origin main && COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress && npm ci --no-audit --no-fund && npm run build && php artisan migrate --force && echo DEPLOY_OK'"
if errorlevel 1 goto :error_ssh

echo.
echo [3/3] Verificando que la app responda...
ssh -i "%SSH_KEY%" %HOST% "curl -s -o /dev/null -w 'HTTP %%{http_code}\n' http://localhost/"
if errorlevel 1 goto :error_ssh

echo.
echo ============================================
echo  DEPLOY OK  -  %URL%
echo ============================================
exit /b 0

:error_git
echo.
echo ERROR: fallo el commit/push de git. Revisa los mensajes de arriba.
exit /b 1

:error_ssh
echo.
echo ERROR: fallo la actualizacion del servidor. Revisa los mensajes de arriba.
exit /b 1
