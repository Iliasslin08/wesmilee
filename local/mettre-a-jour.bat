@echo off
rem Met a jour la plateforme We Smile du cabinet avec la derniere version de GitHub
rem Double-cliquez dessus apres avoir envoye (git push) vos modifications depuis l'autre PC.
cd /d C:\Users\Dell\Documents\wesmile
echo === 1/5 Recuperation du code (GitHub) ===
git pull || goto erreur
echo === 2/5 Bibliotheques PHP ===
call composer install --no-interaction || goto erreur
echo === 3/5 Base de donnees (nouvelles tables) ===
php artisan migrate --force || goto erreur
echo === 4/5 Construction de la plateforme ===
call npm.cmd install || goto erreur
call npm.cmd run build || goto erreur
echo === 5/5 Nettoyage ===
php artisan optimize:clear
echo.
echo Mise a jour terminee. Relancez le serveur si besoin.
pause
exit /b 0
:erreur
echo.
echo ERREUR : la mise a jour s'est arretee. Rien n'est casse, faites une capture de cet ecran.
pause
exit /b 1
