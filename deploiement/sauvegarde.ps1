# =====================================================================
#  WE SMILE - Sauvegarde (base de donnees + documents patients + .env)
#  Lancee automatiquement chaque soir a 20h30 par Windows.
#  Pour la lancer a la main : double-clic sur SAUVEGARDER-MAINTENANT.bat
#
#  Disque externe : nommez-le WESMILE (clic droit sur le disque > Renommer).
#  S'il est branche pendant la sauvegarde, une copie y est faite aussi.
#  Conseil : chiffrez ce disque avec BitLocker (clic droit > Activer BitLocker).
# =====================================================================
param([switch]$Silencieux)

$ErrorActionPreference = 'Continue'
$Projet = 'C:\wesmile'
$Bin = 'C:\xampp\mysql\bin'
$Dest = 'C:\Sauvegardes-Wesmile'
$JoursInterne = 60     # sauvegardes gardees sur le mini PC
$JoursExterne = 365    # sauvegardes gardees sur le disque externe
$GarderMinimum = 10    # on garde toujours au moins les 10 plus recentes
$horodatage = Get-Date -Format 'yyyy-MM-dd_HHmm'
$erreurs = 0

New-Item -ItemType Directory -Force -Path $Dest, (Join-Path $Dest 'base'), (Join-Path $Dest 'documents') | Out-Null
$journal = Join-Path $Dest 'journal.txt'

function Note([string]$t) {
    $ligne = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $t
    [IO.File]::AppendAllText($journal, $ligne + "`r`n", (New-Object System.Text.UTF8Encoding($false)))
    if (-not $Silencieux) { Write-Host "  $ligne" }
}

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

function Nettoyer([string]$dossier, [int]$jours) {
    $tous = @(Get-ChildItem -Path $dossier -Filter 'wesmile-*.zip' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending)
    $limite = (Get-Date).AddDays(-$jours)
    $tous | Select-Object -Skip $GarderMinimum | Where-Object { $_.LastWriteTime -lt $limite } | Remove-Item -Force -ErrorAction SilentlyContinue
}

Note '--- Debut de la sauvegarde ---'

# ----------------------------------------------------------- 1. Base de donnees
$zip = $null
try {
    $cfg = Lire-Env (Join-Path $Projet '.env')
    $db = $cfg['DB_DATABASE']
    $hote = $cfg['DB_HOST']; if (-not $hote) { $hote = '127.0.0.1' }
    $port = $cfg['DB_PORT']; if (-not $port) { $port = '3306' }
    $util = $cfg['DB_USERNAME']; if (-not $util) { $util = 'root' }
    if ($cfg['DB_PASSWORD']) { $env:MYSQL_PWD = $cfg['DB_PASSWORD'] }
    $dump = @('mysqldump.exe', 'mariadb-dump.exe') | ForEach-Object { Join-Path $Bin $_ } | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $dump) { throw "mysqldump introuvable dans $Bin" }

    $tmp = Join-Path $Dest "tmp-$horodatage"
    New-Item -ItemType Directory -Force -Path $tmp | Out-Null
    $sql = Join-Path $tmp 'base-wesmile.sql'
    & $dump -h $hote -P $port -u $util --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "--result-file=$sql" $db 2>&1 | ForEach-Object { Note "mysqldump : $_" }
    if ($LASTEXITCODE -ne 0) {
        & $dump -h $hote -P $port -u $util --single-transaction --quick --default-character-set=utf8mb4 "--result-file=$sql" $db 2>&1 | ForEach-Object { Note "mysqldump : $_" }
    }
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $sql) -or (Get-Item $sql).Length -lt 1024) { throw 'la copie de la base a echoue' }

    # le .env contient la cle APP_KEY : indispensable pour relire les donnees chiffrees
    Copy-Item -LiteralPath (Join-Path $Projet '.env') -Destination (Join-Path $tmp '.env') -Force
    $zip = Join-Path $Dest "base\wesmile-$horodatage.zip"
    Compress-Archive -LiteralPath $sql, (Join-Path $tmp '.env') -DestinationPath $zip -Force
    Remove-Item -LiteralPath $tmp -Recurse -Force
    if (-not (Test-Path $zip)) { throw 'archive non creee' }
    Note ("Base sauvegardee : {0} ({1} Mo)" -f $zip, [math]::Round((Get-Item $zip).Length / 1MB, 1))
} catch {
    $erreurs++
    Note "ERREUR base : $($_.Exception.Message)"
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

# ----------------------------------------------------------- 2. Documents patients
& robocopy (Join-Path $Projet 'storage\app') (Join-Path $Dest 'documents\app') /E /XO /R:1 /W:1 /NP /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { $erreurs++; Note "ERREUR documents (code robocopy $LASTEXITCODE)" } else { Note 'Documents patients sauvegardes' }

Nettoyer (Join-Path $Dest 'base') $JoursInterne

# ----------------------------------------------------------- 3. Disque externe
$volumes = @(Get-Volume -ErrorAction SilentlyContinue | Where-Object { $_.FileSystemLabel -eq 'WESMILE' -and $_.DriveLetter })
if ($volumes.Count -eq 0) {
    Note 'Disque externe WESMILE non branche : pas de copie externe aujourd''hui'
}
foreach ($v in $volumes) {
    $racine = "$($v.DriveLetter):\Wesmile-Sauvegardes"
    try {
        New-Item -ItemType Directory -Force -Path (Join-Path $racine 'base'), (Join-Path $racine 'documents') | Out-Null
        if ($zip -and (Test-Path $zip)) { Copy-Item -LiteralPath $zip -Destination (Join-Path $racine 'base') -Force }
        & robocopy (Join-Path $Projet 'storage\app') (Join-Path $racine 'documents\app') /E /XO /R:1 /W:1 /NP /NFL /NDL /NJH /NJS | Out-Null
        if ($LASTEXITCODE -ge 8) { throw "code robocopy $LASTEXITCODE" }
        Nettoyer (Join-Path $racine 'base') $JoursExterne
        Note "Copie sur le disque externe $($v.DriveLetter): faite"
    } catch {
        $erreurs++
        Note "ERREUR disque externe $($v.DriveLetter): $($_.Exception.Message)"
    }
}

if ($erreurs -eq 0) { Note '--- Sauvegarde terminee sans erreur ---' } else { Note "--- Sauvegarde terminee avec $erreurs erreur(s) ---" }
if (-not $Silencieux) { Write-Host ''; Read-Host '  Appuyez sur Entree pour fermer' }
if ($erreurs -eq 0) { exit 0 } else { exit 1 }
