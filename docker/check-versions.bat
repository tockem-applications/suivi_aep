@echo off
setlocal
cd /d "%~dp0.."
echo Versions dans les conteneurs :
echo.
docker compose exec web /opt/php/bin/php -v 2>nul
docker compose exec web /opt/apache-2.2/bin/httpd -v 2>nul
docker compose exec db mysql --version 2>nul
endlocal
