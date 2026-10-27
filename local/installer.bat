@echo off
rem A lancer UNE fois : clic droit > Executer en tant qu'administrateur
netsh advfirewall firewall add rule name="WeSmile 80" dir=in action=allow protocol=TCP localport=80
schtasks /create /f /tn "WeSmile Taches" /tr "C:\Users\Dell\Documents\wesmile\local\taches.bat" /sc minute /mo 1 /ru SYSTEM
schtasks /create /f /tn "WeSmile Sauvegarde" /tr "C:\Users\Dell\Documents\wesmile\local\sauvegarde.bat" /sc daily /st 20:00 /ru SYSTEM
powercfg /change standby-timeout-ac 0
powercfg /change hibernate-timeout-ac 0
echo Termine. Le PC ne se met plus en veille, les taches et la sauvegarde sont programmees.
pause
