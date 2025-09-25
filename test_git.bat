@echo off
echo [TEST] Demarrage du script de test...
echo [TEST] Dossier courant: %CD%
echo [TEST] Verification de Git...

REM Test de Git
git --version
if errorlevel 1 (
    echo [ERREUR] Git non trouve
    pause
    exit /b 1
)

echo [TEST] Git fonctionne
echo [TEST] Verification du depot Git...

if not exist ".git" (
    echo [INFO] Aucun depot Git detecte
    echo [INFO] Test de clonage...
    
    REM Test simple de clonage
    git clone https://github.com/tockem-applications/suivi_aep test_clone
    if errorlevel 1 (
        echo [ERREUR] Echec du clonage
        pause
        exit /b 1
    )
    
    echo [OK] Clonage reussi
) else (
    echo [INFO] Depot Git detecte
    echo [INFO] Test de fetch...
    git fetch --all
    if errorlevel 1 (
        echo [ERREUR] Echec du fetch
        pause
        exit /b 1
    )
    
    echo [OK] Fetch reussi
)

echo [TEST] Script termine avec succes
pause
