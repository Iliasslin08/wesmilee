# =====================================================================
#  WE SMILE - ETAPE 1 (sur le PC Dell)
#  Copie la plateforme + la base de donnees sur une cle USB,
#  pour l'installer ensuite sur le mini PC.
#
#  Utilisation : clic droit sur ce fichier > "Executer avec PowerShell"
#  Ce script ne modifie RIEN sur ce PC : il lit et copie seulement.
# =====================================================================

$ErrorActionPreference = 'Continue'

function Titre([string]$t) { Write-Host ''; Write-Host "=== $t ===" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "  [OK] $t" -ForegroundColor Green }
function Info([string]$t) { Write-Host "  $t" }
function Attention([string]$t) { Write-Host "  [!] $t" -ForegroundColor Yellow }
function Fin-Erreur([string]$t) {
    Write-Host ''
    Write-Host "  [ERREUR] $t" -ForegroundColor Red
    Write-Host '  Rien n''a ete modifie sur ce PC. Envoyez une capture de cette fenetre a Claude.' -ForegroundColor Red
    Write-Host ''
    Read-Host '  Appuyez sur Entree pour fermer'
    exit 1
}

# Lit le fichier .env (cle=valeur) sans l'afficher
function Lire-Env([string]$chemin) {
    $h = @{}
    foreach ($ligne in [IO.File]::ReadAllLines($chemin)) {
        $t = $ligne.Trim()
        if ($t -eq '' -or $t.StartsWith('#')) { continue }
        $i = $t.IndexOf('=')
        if ($i -lt 1) { continue }
        $k = $t.Substring(0, $i).Trim()
        $v = $t.Substring($i + 1).Trim()
        if ($v.Length -ge 2 -and (($v.StartsWith('"') -and $v.EndsWith('"')) -or ($v.StartsWith("'") -and $v.EndsWith("'")))) {
            $v = $v.Substring(1, $v.Length - 2)
        }
        $h[$k] = $v
    }
    return $h
}

# Taille d'un dossier en ignorant certains sous-dossiers
function Taille-Dossier([string]$racine, [string[]]$exclus) {
    $total = [int64]0
    $pile = New-Object System.Collections.Stack
    $pile.Push($racine)
    while ($pile.Count -gt 0) {
        $d = $pile.Pop()
        if ($exclus -contains $d) { continue }
        try {
            foreach ($f in [IO.Directory]::GetFiles($d)) { $total += (New-Object IO.FileInfo $f).Length }
            foreach ($s in [IO.Directory]::GetDirectories($d)) { $pile.Push($s) }
        } catch { }
    }
    return $total
}

Clear-Host
Write-Host ''
Write-Host '  WE SMILE - Copie vers la cle USB (PC Dell)' -ForegroundColor Cyan
Write-Host '  -----------------------------------------'
Write-Host ''
Write-Host '  Faites cette copie quand PERSONNE n''utilise la plateforme (le soir).'
Write-Host '  Apres la copie, n''utilisez plus la plateforme sur ce PC :'
Write-Host '  tout le monde passera sur le mini PC.'
Write-Host ''

# ---------------------------------------------------------------- 1. Projet
Titre '1. Verification'
$projet = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (-not (Test-Path (Join-Path $projet 'artisan'))) { Fin-Erreur "Le dossier de la plateforme est introuvable ($projet)." }
if (-not (Test-Path (Join-Path $projet '.env'))) { Fin-Erreur "Le fichier .env est introuvable dans $projet." }
Ok "Plateforme : $projet"

$cfg = Lire-Env (Join-Path $projet '.env')
$conn = "$($cfg['DB_CONNECTION'])".ToLower()
if ($conn -ne 'mysql' -and $conn -ne 'mariadb') { Fin-Erreur "La base n'est pas MySQL/MariaDB (DB_CONNECTION=$conn)." }
$db = $cfg['DB_DATABASE']
if (-not $db -or $db -notmatch '^[A-Za-z0-9_]+$') { Fin-Erreur 'Nom de base invalide dans .env (DB_DATABASE).' }
$hote = $cfg['DB_HOST']; if (-not $hote) { $hote = '127.0.0.1' }
$port = $cfg['DB_PORT']; if (-not $port) { $port = '3306' }
$util = $cfg['DB_USERNAME']; if (-not $util) { $util = 'root' }
$env:MYSQL_PWD = "$($cfg['DB_PASSWORD'])"
Ok "Base de donnees : $db"

$bin = 'C:\xampp\mysql\bin'
$dump = @('mysqldump.exe', 'mariadb-dump.exe') | ForEach-Object { Join-Path $bin $_ } | Where-Object { Test-Path $_ } | Select-Object -First 1
$cli = @('mysql.exe', 'mariadb.exe') | ForEach-Object { Join-Path $bin $_ } | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $dump -or -not $cli) { Fin-Erreur "Outils MySQL introuvables dans $bin." }

& $cli -h $hote -P $port -u $util -e 'SELECT 1' *> $null
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'Impossible de joindre MySQL. Ouvrez le XAMPP Control Panel, cliquez sur Start devant MySQL, puis relancez ce script.' }
Ok 'MySQL repond'

# ---------------------------------------------------------------- 2. Cle USB
Titre '2. Choix de la cle USB'
$disques = Get-CimInstance Win32_LogicalDisk | Where-Object { $_.DeviceID -ne 'C:' -and ($_.DriveType -eq 2 -or $_.DriveType -eq 3) }
if (-not $disques) { Fin-Erreur 'Aucune cle USB detectee. Branchez la cle puis relancez.' }
foreach ($d in $disques) {
    $libre = [math]::Round($d.FreeSpace / 1GB, 1)
    Info ("{0}  {1}  ({2} Go libres)" -f $d.DeviceID, $d.VolumeName, $libre)
}
$lettre = (Read-Host '  Tapez la LETTRE de la cle USB (ex. E)').Trim().TrimEnd(':').ToUpper()
$choix = $disques | Where-Object { $_.DeviceID -eq "$($lettre):" }
if (-not $choix) { Fin-Erreur "Lecteur $($lettre): introuvable." }

$dest = "$($lettre):\wesmile-transfert"
if (Test-Path $dest) {
    $r = Read-Host "  Un ancien dossier wesmile-transfert existe sur la cle. Le remplacer ? (O/N)"
    if ($r -notmatch '^[oOyY]') { Fin-Erreur 'Copie annulee.' }
    Remove-Item -LiteralPath $dest -Recurse -Force
}

# Dossiers et fichiers inutiles sur le serveur (ou recrees automatiquement)
$exclusDossiers = @(
    (Join-Path $projet 'node_modules'),
    (Join-Path $projet 'vendor'),
    (Join-Path $projet 'public\storage'),
    (Join-Path $projet 'storage\logs'),
    (Join-Path $projet 'storage\framework\cache'),
    (Join-Path $projet 'storage\framework\sessions'),
    (Join-Path $projet 'storage\framework\views'),
    (Join-Path $projet 'Claude outputs'),
    (Join-Path $projet 'Guides'),
    (Join-Path $projet 'Commercial'),
    (Join-Path $projet 'CNDP')
)
$exclusFichiers = @((Join-Path $projet 'public\hot'))
# Fichiers de travail a la racine (listes Excel, anciennes sauvegardes .sql, rapports .csv)
foreach ($f in @(Get-ChildItem -LiteralPath $projet -File -Force)) {
    if ($f.Extension -in '.xlsx', '.xls', '.csv', '.sql') { $exclusFichiers += $f.FullName }
}

$taille = Taille-Dossier $projet $exclusDossiers
$besoin = $taille + 200MB
$tailleMo = [math]::Round($taille / 1MB)
if ($choix.FreeSpace -lt $besoin) { Fin-Erreur ("Pas assez de place sur la cle : il faut environ {0} Mo." -f [math]::Round($besoin / 1MB)) }
Ok ("Place suffisante (environ {0} Mo a copier)" -f $tailleMo)

$conf = Read-Host '  Personne n''utilise la plateforme en ce moment ? On commence ? (O/N)'
if ($conf -notmatch '^[oOyY]') { Fin-Erreur 'Copie annulee.' }

New-Item -ItemType Directory -Path $dest -Force | Out-Null

# ---------------------------------------------------------------- 3. Base
Titre '3. Copie de la base de donnees'
$fichierSql = Join-Path $dest 'base-wesmile.sql'
& $dump -h $hote -P $port -u $util --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "--result-file=$fichierSql" $db
if ($LASTEXITCODE -ne 0) {
    Attention 'Nouvel essai sans les procedures stockees...'
    & $dump -h $hote -P $port -u $util --single-transaction --quick --default-character-set=utf8mb4 "--result-file=$fichierSql" $db
}
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $fichierSql) -or (Get-Item $fichierSql).Length -lt 1024) {
    Fin-Erreur 'La copie de la base a echoue.'
}
Ok ("Base copiee ({0} Mo)" -f [math]::Round((Get-Item $fichierSql).Length / 1MB, 1))
Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue

# ---------------------------------------------------------------- 4. Fichiers
Titre '4. Copie de la plateforme et des documents patients'
Info 'Cela peut prendre quelques minutes...'
$argsRobo = @($projet, (Join-Path $dest 'wesmile'), '/E', '/R:1', '/W:1', '/NP', '/NFL', '/NDL', '/NJH', '/XD') + $exclusDossiers + @('/XF') + $exclusFichiers
& robocopy @argsRobo | Out-Null
if ($LASTEXITCODE -ge 8) { Fin-Erreur "La copie des fichiers a echoue (code robocopy $LASTEXITCODE)." }
Ok 'Plateforme et documents copies'

$lisezMoi = @"
WE SMILE - DOSSIER DE TRANSFERT (CONFIDENTIEL)
Cree le $(Get-Date -Format 'dd/MM/yyyy HH:mm') depuis le PC Dell.

Contenu : la plateforme, les documents patients, le fichier .env (cle de chiffrement)
et la base de donnees (base-wesmile.sql). Ne pretez pas cette cle USB.

SUR LE MINI PC :
1. Installez d'abord XAMPP 8.5, Composer, Node.js et Git.
2. Ouvrez ce dossier : wesmile\deploiement
3. Clic droit sur 2-installer-sur-mini-pc.ps1 > Executer avec PowerShell

Apres l'installation : SUPPRIMEZ ce dossier wesmile-transfert de la cle USB.
"@
[IO.File]::WriteAllText((Join-Path $dest 'LISEZ-MOI.txt'), $lisezMoi, (New-Object System.Text.UTF8Encoding($true)))

Titre 'TERMINE'
Ok "Tout est sur la cle : $dest"
Write-Host ''
Write-Host '  Ensuite :' -ForegroundColor Cyan
Info '1. Arretez la plateforme sur ce PC (fermez la fenetre "php artisan serve").'
Info '2. Branchez la cle sur le mini PC et lancez :'
Info "   $($lettre):\wesmile-transfert\wesmile\deploiement\2-installer-sur-mini-pc.ps1"
Attention 'La cle contient des donnees patients : gardez-la sur vous et videz-la apres.'
Write-Host ''
Read-Host '  Appuyez sur Entree pour fermer'
