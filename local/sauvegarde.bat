@echo off
rem Sauvegarde quotidienne de We Smile : base de donnees + fichiers (radios, photos)
rem Changez DEST pour un disque externe / cle USB (ex : E:\Sauvegardes-WeSmile)
set DEST=D:\Sauvegardes-WeSmile
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set JOUR=%%i
if not exist "%DEST%" mkdir "%DEST%"
"C:\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction wesmile > "%DEST%\wesmile-%JOUR%.sql"
robocopy "C:\Users\Dell\Documents\wesmile\storage\app" "%DEST%\fichiers" /MIR /NFL /NDL /NJH /NJS
rem garde 30 jours de sauvegardes
forfiles /p "%DEST%" /m wesmile-*.sql /d -30 /c "cmd /c del @path" 2>nul
echo Sauvegarde terminee : %DEST%\wesmile-%JOUR%.sql
