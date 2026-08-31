@echo off
REM Compilation de la documentation utilisateur (pdfLaTeX)
setlocal
cd /d "%~dp0"

where pdflatex >nul 2>&1
if errorlevel 1 (
    echo ERREUR: pdflatex introuvable. Installez MiKTeX ou TeX Live et ajoutez-le au PATH.
    exit /b 1
)

echo Compilation 1/2...
pdflatex -interaction=nonstopmode -halt-on-error main.tex
if errorlevel 1 goto :error

echo Compilation 2/2 (table des matieres)...
pdflatex -interaction=nonstopmode -halt-on-error main.tex
if errorlevel 1 goto :error

if exist main.pdf (
    copy /Y main.pdf ..\doc_utilisateur_web_app.pdf >nul
    echo.
    echo OK: main.pdf et ..\doc_utilisateur_web_app.pdf generes.
) else (
    echo ERREUR: main.pdf non cree.
    exit /b 1
)
exit /b 0

:error
echo.
echo Echec de compilation. Consultez main.log
exit /b 1
