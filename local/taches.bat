@echo off
rem Lance les taches automatiques de We Smile (rappels SMS...) - appele chaque minute par Windows
cd /d C:\Users\Dell\Documents\wesmile
C:\xampp\php\php.exe artisan schedule:run >> storage\logs\taches.log 2>&1
