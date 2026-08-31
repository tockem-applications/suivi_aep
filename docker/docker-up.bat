@echo off
setlocal
cd /d "%~dp0.."
if not exist ".env" (
    echo Creation de .env depuis .env.example...
    copy /Y ".env.example" ".env" >nul
)
echo Demarrage (Apache 2.2 + PHP 5.3 + MySQL 5.1.73)...
echo Si premiere install ou reseau OK : docker compose pull db
docker compose up -d
if errorlevel 1 (
    echo Echec. Verifiez que Docker Desktop est lance.
    exit /b 1
)

REM Re-lance l'init schema si la table aep est absente
docker compose run --rm db-init
echo.
echo Rebuild web uniquement si Dockerfile modifie et reseau OK :
echo   docker compose build web
echo.
echo Application : http://localhost:8080/index.php
echo MySQL (hote)  : localhost:3307  user=suivi  pass=suivi
echo.
docker compose ps
echo.
call "%~dp0check-versions.bat"
endlocal
