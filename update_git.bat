@echo off
echo [INFO] Demarrage du script update_git.bat
echo [INFO] Dossier courant: %CD%
setlocal ENABLEDELAYEDEXPANSION

REM ==============================
REM  Script de mise à jour Git (avec clonage automatique)
REM  Usage:
REM    update_git.bat [--auto-stash] [--submodules]
REM  Notes:
REM    - Clone automatiquement le dépôt s'il n'existe pas
REM    - Nécessite Git installé
REM ==============================

REM Configuration du dépôt distant
set "GIT_REMOTE=https://github.com/tockem-applications/suivi_aep"
set "DEFAULT_BRANCH=recouvrement_branch"

REM 1) Détection de Git
echo [DEBUG] Detection de Git...
set "GIT_BIN=git"
where %GIT_BIN% >nul 2>nul
if errorlevel 1 (
  echo [DEBUG] Git non trouve dans PATH, essai du chemin par defaut...
  REM Chemin par défaut Git pour Windows (adapter si besoin)
  set "GIT_BIN=C:\Program Files\Git\bin\git.exe"
)

REM Vérif Git
echo [DEBUG] Test de Git avec: %GIT_BIN%
"%GIT_BIN%" --version || (
  echo.
  echo ========================================
  echo            ERREUR CRITIQUE
  echo ========================================
  echo.
  echo [ERROR] Git introuvable !
  echo [ERROR] Modifiez GIT_BIN dans ce script si besoin.
  echo [DEBUG] Chemin teste: %GIT_BIN%
  echo.
  echo Appuyez sur une touche pour fermer...
  pause
  exit /b 1
)
echo [DEBUG] Git trouve et fonctionnel.

REM Empêcher les prompts interactifs (bloquants)
set "GIT_TERMINAL_PROMPT=0"
set "GIT_MERGE_AUTOEDIT=no"
set "GIT_EDITOR=true"

REM Vérifier la présence d'un dépôt
if not exist ".git" (
  echo [INFO] Aucun depot Git detecte dans ce dossier.
  echo [INFO] Clonage du depot depuis: %GIT_REMOTE%
  echo [INFO] Branche par defaut: %DEFAULT_BRANCH%
  
  REM Clonage simple
  echo [INFO] Clonage en cours...
  call "%GIT_BIN%" clone -b "%DEFAULT_BRANCH%" "%GIT_REMOTE%" suivi_reseau
  if errorlevel 1 (
    echo.
    echo ========================================
    echo            ERREUR DE CLONAGE
    echo ========================================
    echo.
    echo [ERROR] Echec du clonage du depot !
    echo [ERROR] Verifiez l'URL et votre connexion Internet.
    echo.
    echo Appuyez sur une touche pour fermer...
    pause
    exit /b 2
  )
  
  echo [OK] Depot clone avec succes dans le dossier: suivi_reseau
  echo [INFO] Navigation vers le nouveau repertoire...
  
  REM Naviguer vers le nouveau répertoire
  cd suivi_reseau
  if errorlevel 1 (
    echo.
    echo ========================================
    echo            ERREUR DE NAVIGATION
    echo ========================================
    echo.
    echo [ERROR] Impossible de naviguer vers le dossier suivi_reseau !
    echo [ERROR] Verifiez les permissions d'acces.
    echo.
    echo Appuyez sur une touche pour fermer...
    pause
    exit /b 3
  )
  
  echo [INFO] Relance du script dans le nouveau repertoire...
  echo [INFO] Dossier courant: %CD%
  
  REM Relancer le script dans le nouveau répertoire
  call "%~dp0%~nx0" %*
  exit /b %errorlevel%
)

REM Marquer le dépôt courant comme sûr (safe.directory)
for /f "delims=" %%P in ("%CD%") do set "REPO_DIR=%%~fP"
"%GIT_BIN%" config --global --add safe.directory "%REPO_DIR%" >nul 2>nul
"%GIT_BIN%" config --global --add safe.directory "%REPO_DIR:\=/%" >nul 2>nul

REM État initial
call "%GIT_BIN%" status -sb

REM Déterminer la branche courante
for /f "usebackq tokens=*" %%b in (`"%GIT_BIN%" rev-parse --abbrev-ref HEAD`) do set "BRANCH=%%b"
if not defined BRANCH (
  echo.
  echo ========================================
  echo            ERREUR DE BRANCHE
  echo ========================================
  echo.
  echo [ERROR] Impossible de determiner la branche courante !
  echo [ERROR] Verifiez que vous etes dans un depot Git valide.
  echo.
  echo Appuyez sur une touche pour fermer...
  pause
  exit /b 3
)

echo Branche courante: %BRANCH%

REM Options
set "DO_STASH=false"
set "DO_SUBMODULES=false"
for %%A in (%*) do (
  if /I "%%~A"=="--auto-stash" set "DO_STASH=true"
  if /I "%%~A"=="--submodules" set "DO_SUBMODULES=true"
)

REM Stash auto si demandé et si des modifs existent
if /I "%DO_STASH%"=="true" (
  for /f "usebackq tokens=*" %%s in (`"%GIT_BIN%" status --porcelain`) do set "HAS_CHANGES=1"
  if defined HAS_CHANGES (
    echo [INFO] Modifications locales detectees ^> stash...
    call "%GIT_BIN%" stash push -u -m "auto-stash avant update" || echo [WARN] Echec du stash.
  )
)

REM Fetch & Pull
echo [INFO] Fetch distant...
call "%GIT_BIN%" fetch --all --prune
if errorlevel 1 (
  echo.
  echo ========================================
  echo            ERREUR DE FETCH
  echo ========================================
  echo.
  echo [ERROR] Echec du fetch du depot distant !
  echo [ERROR] Verifiez votre connexion Internet et l'URL du depot.
  echo.
  echo Appuyez sur une touche pour fermer...
  pause
  exit /b 4
)

echo [INFO] Pull depuis origin/%BRANCH% ...
call "%GIT_BIN%" pull origin "%BRANCH%"
if errorlevel 1 (
  echo.
  echo ========================================
  echo            ERREUR DE PULL
  echo ========================================
  echo.
  echo [ERROR] Echec du pull depuis le depot distant !
  echo [ERROR] Verifiez le suivi de branche et les conflits.
  echo [ERROR] Vous pouvez avoir des modifications locales non committees.
  echo.
  echo Appuyez sur une touche pour fermer...
  pause
  exit /b 5
)

REM Submodules si demandé
if /I "%DO_SUBMODULES%"=="true" (
  echo [INFO] Mise a jour des sous-modules...
  call "%GIT_BIN%" submodule update --init --recursive || echo [WARN] Echec maj sous-modules.
)

REM Etat final
echo.
echo [OK] Mise a jour terminee sur %BRANCH%.
call "%GIT_BIN%" log -1 --oneline

REM Copier les scripts sur le bureau pour un accès facile
echo [INFO] Copie des scripts sur le bureau...

REM Copier update_git.bat
echo [INFO] Copie de update_git.bat...
copy "update_git.bat" "%DESKTOP%\Update Suivi Reseau.bat" >nul
if errorlevel 1 (
    echo [WARN] Impossible de copier update_git.bat sur le bureau
) else (
    echo [OK] update_git.bat copie sur le bureau
)

REM Copier start.bat
echo [INFO] Copie de start.bat...
copy "start.bat" "%DESKTOP%\Start Suivi Reseau.bat" >nul
if errorlevel 1 (
    echo [WARN] Impossible de copier start.bat sur le bureau
) else (
    echo [OK] start.bat copie sur le bureau
)

echo [OK] Scripts copies sur le bureau:
echo   - Update Suivi Reseau.bat
echo   - Start Suivi Reseau.bat

REM Message de succès final
echo.
echo ========================================
echo           MISE A JOUR REUSSIE
echo ========================================
echo.
echo [SUCCESS] Le depot Git a ete mis a jour avec succes !
echo [SUCCESS] Branche: %BRANCH%
echo [SUCCESS] Scripts copies sur le bureau
echo [SUCCESS] Vous pouvez maintenant utiliser l'application
echo.
echo Appuyez sur une touche pour fermer...
pause

endlocal
exit /b 0
