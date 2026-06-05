@echo off
setlocal
cd /d "%~dp0.."

set "SQL_FILE=app-setup\App_vide.sql"
if not exist "%SQL_FILE%" (
    echo Fichier introuvable : %SQL_FILE%
    exit /b 1
)

echo Import forcé du schema App_vide.sql (recree la base)...
docker compose ps db 2>nul | findstr /i "running" >nul
if errorlevel 1 (
    echo Demarrez d'abord : docker compose up -d
    exit /b 1
)

docker compose run --rm db-init
if errorlevel 1 (
    echo Echec db-init. Essayez : docker compose exec db mysql -uroot -proot ^< "%SQL_FILE%"
    exit /b 1
)

echo.
echo OK. Verifiez :
echo   docker compose exec db mysql -uroot -proot -e "USE suivi_aep_fokoue; SHOW TABLES; SELECT id, libele FROM aep;"
endlocal
