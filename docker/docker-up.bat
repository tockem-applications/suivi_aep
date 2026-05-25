@echo off
setlocal
cd /d "%~dp0.."
if not exist ".env" (
    echo Creation de .env depuis .env.example...
    copy /Y ".env.example" ".env" >nul
)
echo Telechargement / demarrage (Apache 2.2 + PHP 5.3 + MySQL 5.1.73)...
docker compose pull db 2>nul
docker compose up -d --build
if errorlevel 1 (
    echo Echec. Verifiez que Docker Desktop est lance.
    exit /b 1
)
echo.
echo Application : http://localhost:8080/index.php
echo MySQL (hote)  : localhost:3307  user=suivi  pass=suivi
echo.
docker compose ps
echo.
call "%~dp0check-versions.bat"
endlocal
