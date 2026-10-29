@echo off
rem Copie de securite HORS DU CABINET : base de donnees + scans/photos sur un disque USB chiffre.
rem A lancer chaque vendredi, disque USB branche. Puis le disque sort du cabinet.
setlocal
cd /d C:\Users\Dell\Documents\wesmile
echo.
echo === Copie de securite We Smile vers un disque USB ===
echo.
set /p LETTRE=Lettre du disque USB (ex. E) : 
set USB=%LETTRE%:\WeSmile-Sauvegarde
if not exist %LETTRE%:\ (
  echo ERREUR : aucun disque %LETTRE%: branche.
  pause
  exit /b 1
)
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set JOUR=%%i
if not exist "%USB%\base" mkdir "%USB%\base"
echo 1/3 Base de donnees...
"C:\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction wesmile > "%USB%\base\wesmile-%JOUR%.sql" || goto erreur
echo 2/3 Scans, photos et radios (seuls les nouveaux fichiers sont copies)...
robocopy "C:\Users\Dell\Documents\wesmile\storage\app" "%USB%\fichiers" /MIR /NFL /NDL /NJH /NP
if %ERRORLEVEL% GEQ 8 goto erreur
echo 3/3 Nettoyage : on garde les 8 dernieres copies de la base...
powershell -NoProfile -Command "Get-ChildItem '%USB%\base\wesmile-*.sql' | Sort-Object Name -Descending | Select-Object -Skip 8 | Remove-Item"
echo %JOUR% : copie OK >> "%USB%\journal.txt"
for %%F in ("%USB%\base\wesmile-%JOUR%.sql") do echo Taille de la base copiee : %%~zF octets
echo.
echo TERMINE. Retirez le disque (icone USB > Ejecter) et sortez-le du cabinet.
pause
exit /b 0
:erreur
echo.
echo ERREUR pendant la copie. Le disque n'est pas a jour : faites une capture de cet ecran.
pause
exit /b 1
