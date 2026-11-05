# =====================================================================
#  WE SMILE - Mise a jour de la plateforme (sur le mini PC)
#  A lancer le soir ou a midi, quand personne n'utilise la plateforme.
#  1) sauvegarde  2) nouvelle version (Git)  3) composants  4) pages
#  5) base de donnees  6) cache et redemarrage d'Apache
# =====================================================================

$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$PSCommandPath`"")
    exit
}

$ErrorActionPreference = 'Continue'
$Projet = 'C:\wesmile'
$PhpDir = 'C:\xampp\php'
$Php = Join-Path $PhpDir 'php.exe'
$enMaintenance = $false

function Titre([string]$t) { Write-Host ''; Write-Host "=== $t ===" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "  [OK] $t" -ForegroundColor Green }
function Fin-Erreur([string]$t) {
    Write-Host ''
    Write-Host "  [ERREUR] $t" -ForegroundColor Red
    if ($enMaintenance) { & $Php (Join-Path $Projet 'artisan') up | Out-Null }
    Write-Host '  La sauvegarde de ce soir est dans C:\Sauvegardes-Wesmile. Envoyez une capture a Claude.' -ForegroundColor Red
    Write-Host ''
    Read-Host '  Appuyez sur Entree pour fermer'
    exit 1
}
function Demander-Oui([string]$q) { $r = Read-Host "  $q (O/N)"; return ($r -match '^[oOyY]') }

$m = [Environment]::GetEnvironmentVariable('Path', 'Machine')
$u = [Environment]::GetEnvironmentVariable('Path', 'User')
$env:Path = "$PhpDir;$m;$u"

Clear-Host
Write-Host ''
Write-Host '  WE SMILE - Mise a jour' -ForegroundColor Cyan
Write-Host '  La plateforme sera indisponible quelques minutes.'
if (-not (Demander-Oui 'Personne ne l''utilise en ce moment ? On commence ?')) { exit }

Titre '1/6  Sauvegarde avant mise a jour'
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Projet 'deploiement\sauvegarde.ps1') -Silencieux
if ($LASTEXITCODE -ne 0) {
    if (-not (Demander-Oui 'La sauvegarde a signale une erreur (voir C:\Sauvegardes-Wesmile\journal.txt). Continuer quand meme ?')) { exit 1 }
} else { Ok 'Sauvegarde faite' }

Titre '2/6  Nouvelle version (Git)'
if (-not (Test-Path (Join-Path $Projet '.git'))) { Fin-Erreur 'Pas de depot Git dans C:\wesmile.' }
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Fin-Erreur 'Git n''est pas installe : https://git-scm.com' }
$surs = @(& git config --global --get-all safe.directory 2>$null)
if ($surs -notcontains 'C:/wesmile') { & git config --global --add safe.directory 'C:/wesmile' }
Set-Location $Projet
& git pull --ff-only
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'git pull a echoue (fichiers modifies sur le mini PC, ou pas de connexion).' }
Ok 'Nouvelle version recuperee'

& $Php artisan down --retry=60 | Out-Null
$enMaintenance = $true

Titre '3/6  Composants PHP'
$env:COMPOSER_ALLOW_SUPERUSER = '1'
& composer install --no-dev --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'composer install a echoue.' }

Titre '4/6  Pages de la plateforme'
& npm.cmd ci
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'npm ci a echoue.' }
& npm.cmd run build
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'npm run build a echoue.' }

Titre '5/6  Base de donnees'
& $Php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'php artisan migrate a echoue.' }

Titre '6/6  Cache et redemarrage'
& $Php artisan optimize
if ($LASTEXITCODE -ne 0) { & $Php artisan optimize:clear | Out-Null }
& $Php artisan up | Out-Null
$enMaintenance = $false
Restart-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3
$code = 0
try { $code = [int](Invoke-WebRequest -Uri 'http://localhost/login' -UseBasicParsing -TimeoutSec 60).StatusCode } catch { }
if ($code -eq 200) { Ok 'Mise a jour terminee : la plateforme marche' } else { Write-Host "  [!] La page de connexion repond avec le code $code : regardez C:\wesmile\storage\logs\laravel.log" -ForegroundColor Yellow }
Write-Host ''
Read-Host '  Appuyez sur Entree pour fermer'
