@echo off
rem Sauvegarde quotidienne de We Smile : base de donnees + fichiers (scans, photos, radios)
rem Si un disque externe E: est branche, la sauvegarde va dessus, sinon dans C:\Sauvegardes-WeSmile
set DEST=C:\Sauvegardes-WeSmile
if exist E:\ set DEST=E:\Sauvegardes-WeSmile
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set JOUR=%%i
if not exist "%DEST%" mkdir "%DEST%"
"C:\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction wesmile > "%DEST%\wesmile-%JOUR%.sql"
robocopy "C:\Users\Dell\Documents\wesmile\storage\app" "%DEST%\fichiers" /MIR /NFL /NDL /NJH /NJS /NP
rem garde 30 jours de sauvegardes de la base
forfiles /p "%DEST%" /m wesmile-*.sql /d -30 /c "cmd /c del @path" 2>nul
echo %JOUR% : sauvegarde OK dans %DEST% >> "%DEST%\journal.txt"
echo Sauvegarde terminee : %DEST%\wesmile-%JOUR%.sql
