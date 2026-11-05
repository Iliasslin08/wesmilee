# =====================================================================
#  WE SMILE - ETAPE 2 (sur le mini PC)
#  Installe la plateforme en mode serveur : Apache et MySQL demarrent
#  tout seuls avec Windows, sans ouvrir de session.
#
#  Avant : installez XAMPP 8.5 (dans C:\xampp), Composer, Node.js LTS et Git.
#  Utilisation : clic droit sur ce fichier > "Executer avec PowerShell"
#  Vous pouvez le relancer sans risque : il ne remplace jamais une base deja remplie.
# =====================================================================

# --- Relance automatique en administrateur
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Start-Process -FilePath 'powershell.exe' -Verb RunAs -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$PSCommandPath`"")
    exit
}

$ErrorActionPreference = 'Continue'
$Xampp = 'C:\xampp'
$PhpDir = Join-Path $Xampp 'php'
$Php = Join-Path $PhpDir 'php.exe'
$Projet = 'C:\wesmile'
$Temp = 'C:\wesmile-temp'
$Journal = 'C:\wesmile-installation.log'
$PhpMin = [version]'8.4.1'
$NodeMin = [version]'20.19.0'

try { Start-Transcript -Path $Journal -Append | Out-Null } catch { }

function Titre([string]$t) { Write-Host ''; Write-Host "=== $t ===" -ForegroundColor Cyan }
function Ok([string]$t) { Write-Host "  [OK] $t" -ForegroundColor Green }
function Info([string]$t) { Write-Host "  $t" }
function Attention([string]$t) { Write-Host "  [!] $t" -ForegroundColor Yellow }
function Fin-Erreur([string]$t) {
    Write-Host ''
    Write-Host "  [ERREUR] $t" -ForegroundColor Red
    Write-Host "  Envoyez une capture de cette fenetre a Claude (journal : $Journal)." -ForegroundColor Red
    Write-Host '  Vous pourrez relancer ce script apres correction : il reprend sans rien abimer.' -ForegroundColor Red
    try { Stop-Transcript | Out-Null } catch { }
    Write-Host ''
    Read-Host '  Appuyez sur Entree pour fermer'
    exit 1
}
function Demander-Oui([string]$q) { $r = Read-Host "  $q (O/N)"; return ($r -match '^[oOyY]') }
function Ecrire-Texte([string]$chemin, [string]$texte) {
    [IO.File]::WriteAllText($chemin, $texte, (New-Object System.Text.UTF8Encoding($false)))
}
function Fin-De-Ligne([string]$texte) { if ($texte.Contains("`r`n")) { return "`r`n" } return "`n" }

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

# Change quelques lignes du .env sans toucher au reste (APP_KEY reste identique)
function Modifier-Env([string]$chemin, [hashtable]$valeurs) {
    $texte = [IO.File]::ReadAllText($chemin)
    $nl = Fin-De-Ligne $texte
    $lignes = New-Object System.Collections.Generic.List[string]
    foreach ($l in ($texte -split '\r?\n')) { $lignes.Add($l) }
    foreach ($cle in $valeurs.Keys) {
        $nouvelle = "$cle=$($valeurs[$cle])"
        $trouve = $false
        for ($i = 0; $i -lt $lignes.Count; $i++) {
            if ($lignes[$i] -match ('^\s*' + [regex]::Escape($cle) + '\s*=')) { $lignes[$i] = $nouvelle; $trouve = $true; break }
        }
        if (-not $trouve) {
            if ($lignes.Count -gt 0 -and $lignes[$lignes.Count - 1] -eq '') { $lignes.Insert($lignes.Count - 1, $nouvelle) } else { $lignes.Add($nouvelle) }
        }
    }
    Ecrire-Texte $chemin ($lignes -join $nl)
}

function Rafraichir-Path {
    $m = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $u = [Environment]::GetEnvironmentVariable('Path', 'User')
    $env:Path = "$PhpDir;$m;$u"
}

function Port-Occupe([int]$port) {
    $c = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $c) { return $null }
    $p = Get-Process -Id $c.OwningProcess -ErrorAction SilentlyContinue
    if ($p) { return $p.ProcessName }
    return "processus $($c.OwningProcess)"
}

function Parent-Sur([string]$chemin) {
    try { return (Split-Path -Path $chemin -Parent) } catch { return $null }
}

Clear-Host
Write-Host ''
Write-Host '  WE SMILE - Installation du serveur (mini PC)' -ForegroundColor Cyan
Write-Host '  --------------------------------------------'
Write-Host ''
Write-Host '  Ce script va :'
Info '- copier la plateforme dans C:\wesmile (si elle n''y est pas deja)'
Info '- installer ses composants et preparer les pages (connexion Internet necessaire)'
Info '- configurer Apache et MySQL comme services Windows (demarrage automatique)'
Info '- importer la base de donnees (seulement si la base est vide)'
Info '- ouvrir le port 80 dans le pare-feu, pour le reseau local seulement'
Info '- activer le Bureau a distance et empecher la mise en veille'
Info '- programmer les rappels automatiques et une sauvegarde chaque soir a 20h30'
Write-Host ''
if (-not (Demander-Oui 'On continue ?')) { exit }

# ================================================================= 1
Titre '1/12  Logiciels necessaires'
Rafraichir-Path
$manque = @()
$mysqld = $null; $mysqlCli = $null
if (-not (Test-Path $Php) -or -not (Test-Path (Join-Path $Xampp 'apache\bin\httpd.exe'))) {
    $manque += 'XAMPP 8.5 installe dans C:\xampp : https://www.apachefriends.org/download.html'
} else {
    $v = "$(& $Php -n -r 'echo PHP_VERSION;')".Trim()
    try { $vp = [version](($v -split '-')[0]) } catch { $vp = [version]'0.0' }
    if ($vp -lt $PhpMin) { $manque += "XAMPP 8.5 (vous avez PHP $v ; la plateforme demande au moins PHP 8.4.1)" } else { Ok "PHP $v" }
    $mysqld = @('mysqld.exe', 'mariadbd.exe') | ForEach-Object { Join-Path $Xampp "mysql\bin\$_" } | Where-Object { Test-Path $_ } | Select-Object -First 1
    $mysqlCli = @('mysql.exe', 'mariadb.exe') | ForEach-Object { Join-Path $Xampp "mysql\bin\$_" } | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $mysqld -or -not $mysqlCli) { $manque += 'MySQL de XAMPP (C:\xampp\mysql) introuvable : reinstallez XAMPP avec MySQL' } else { Ok 'MySQL (XAMPP)' }
}
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    $manque += 'Composer : https://getcomposer.org/Composer-Setup.exe (choisir C:\xampp\php\php.exe)'
} else { Ok 'Composer' }
if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    $manque += 'Node.js (version LTS) : https://nodejs.org'
} else {
    $nv = "$(& node -v)".Trim().TrimStart('v')
    try { $nvp = [version]$nv } catch { $nvp = [version]'0.0' }
    if ($nvp -lt $NodeMin) { $manque += "Node.js plus recent (vous avez $nv ; il faut 20.19 ou plus)" } else { Ok "Node.js $nv" }
}
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Attention 'Git n''est pas installe (utile pour les mises a jour) : https://git-scm.com'
} else { Ok 'Git' }
if ($manque.Count -gt 0) {
    Write-Host ''
    Write-Host '  Il manque :' -ForegroundColor Yellow
    foreach ($m in $manque) { Write-Host "   - $m" -ForegroundColor Yellow }
    Fin-Erreur 'Installez ce qui manque, puis relancez ce script.'
}

# ================================================================= 2
Titre '2/12  Fichiers de la plateforme'
# Dossier de transfert cree par le script 1 (cle USB)
$transfert = $null
$parent = Parent-Sur $PSScriptRoot
if ($parent -and ($parent -ne $Projet) -and (Test-Path (Join-Path $parent 'artisan'))) { $transfert = $parent }
if (-not $transfert) {
    foreach ($dr in (Get-PSDrive -PSProvider FileSystem)) {
        $c = Join-Path $dr.Root 'wesmile-transfert\wesmile'
        if (Test-Path (Join-Path $c 'artisan')) { $transfert = $c; break }
    }
}
$source = $transfert
if (Test-Path (Join-Path $Projet 'artisan')) {
    Ok "Plateforme deja presente dans $Projet (copie Git ou installation precedente)"
    # Le .env et les documents patients ne passent jamais par Git : on les prend sur la cle USB
    if ($transfert) {
        if (-not (Test-Path (Join-Path $Projet '.env')) -and (Test-Path (Join-Path $transfert '.env'))) {
            Copy-Item -LiteralPath (Join-Path $transfert '.env') -Destination (Join-Path $Projet '.env')
            Ok 'Fichier .env copie depuis la cle USB'
        }
        $docs = @(Get-ChildItem -Path (Join-Path $Projet 'storage\app\private') -Recurse -File -Force -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne '.gitignore' })
        if ($docs.Count -eq 0 -and (Test-Path (Join-Path $transfert 'storage\app'))) {
            Info 'Copie des documents patients depuis la cle USB...'
            & robocopy (Join-Path $transfert 'storage\app') (Join-Path $Projet 'storage\app') /E /R:1 /W:1 /NP /NFL /NDL /NJH /NJS | Out-Null
            if ($LASTEXITCODE -ge 8) { Fin-Erreur "La copie des documents a echoue (code robocopy $LASTEXITCODE)." }
            Ok 'Documents patients copies'
        }
    }
} elseif ($transfert) {
    Info "Copie depuis $transfert ..."
    & robocopy $transfert $Projet /E /R:1 /W:1 /NP /NFL /NDL /NJH /NJS | Out-Null
    if ($LASTEXITCODE -ge 8) { Fin-Erreur "La copie a echoue (code robocopy $LASTEXITCODE)." }
    Ok "Plateforme copiee dans $Projet"
} else {
    Fin-Erreur 'Plateforme introuvable. Lancez d''abord le script 1 sur le PC Dell (copie sur cle USB), puis branchez la cle sur ce PC.'
}
$envFile = Join-Path $Projet '.env'
if (-not (Test-Path $envFile)) { Fin-Erreur "Fichier .env manquant dans $Projet : branchez la cle USB du script 1, ou copiez le .env du PC Dell (il contient la cle APP_KEY)." }
$nbDocs = @(Get-ChildItem -Path (Join-Path $Projet 'storage\app\private') -Recurse -File -Force -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne '.gitignore' }).Count
if ($nbDocs -eq 0) { Attention 'Aucun document patient dans storage\app\private : branchez la cle USB du script 1 et relancez ce script.' } else { Ok "$nbDocs documents patients presents" }

# ================================================================= 3
Titre '3/12  Preparation des dossiers'
Get-ChildItem -Path (Join-Path $Projet 'bootstrap\cache') -Filter '*.php' -ErrorAction SilentlyContinue | Remove-Item -Force
$hot = Join-Path $Projet 'public\hot'
if (Test-Path $hot) { Remove-Item $hot -Force }
$lien = Join-Path $Projet 'public\storage'
if (Test-Path $lien) {
    $it = Get-Item $lien -Force
    if (-not ($it.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        $copie = Join-Path $Projet ('storage\public-storage-copie-' + (Get-Date -Format 'yyyyMMdd-HHmm'))
        Move-Item -LiteralPath $lien -Destination $copie
        Attention "Ancien dossier public\storage deplace dans $copie (rien n'est perdu)"
    }
}
foreach ($d in @('storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\app\private', 'storage\app\public', 'bootstrap\cache')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $Projet $d) | Out-Null
}
Ok 'Dossiers prets'

# ================================================================= 4
Titre '4/12  Fichier .env'
$cfg = Lire-Env $envFile
if (-not $cfg['APP_KEY']) {
    Fin-Erreur 'APP_KEY est vide dans .env. Recopiez le .env du PC Dell. Ne lancez JAMAIS key:generate : les donnees chiffrees deviendraient illisibles.'
}
$conn = "$($cfg['DB_CONNECTION'])".ToLower()
if ($conn -ne 'mysql' -and $conn -ne 'mariadb') { Fin-Erreur "DB_CONNECTION doit etre mysql dans .env (actuellement : $conn)." }
$db = $cfg['DB_DATABASE']
if (-not $db -or $db -notmatch '^[A-Za-z0-9_]+$') { Fin-Erreur 'Nom de base invalide dans .env (DB_DATABASE).' }
$dbUser = $cfg['DB_USERNAME']; if (-not $dbUser) { $dbUser = 'root' }
$dbPass = "$($cfg['DB_PASSWORD'])"
if ($dbUser -notmatch '^[A-Za-z0-9_.\-]+$') { Fin-Erreur 'Nom d''utilisateur invalide dans .env (DB_USERNAME).' }

$ipObj = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'vEthernet|Loopback|VMware|VirtualBox|WSL|Bluetooth' } |
    Sort-Object @{ Expression = { if ($_.InterfaceAlias -match 'Ethernet') { 0 } else { 1 } } } |
    Select-Object -First 1
if (-not $ipObj) { Fin-Erreur 'Aucune adresse reseau : branchez le cable reseau, puis relancez.' }
$ip = $ipObj.IPAddress
Info "Adresse du mini PC : $ip ($($ipObj.InterfaceAlias))"
if ($ipObj.InterfaceAlias -notmatch 'Ethernet') { Attention 'Le mini PC est en Wi-Fi : un cable reseau est plus fiable pour un serveur.' }
$autre = Read-Host "  Entree pour garder $ip (ou tapez une autre adresse)"
if ($autre -and $autre.Trim()) { $ip = $autre.Trim() }

$bak = Join-Path $Projet '.env.backup'
if (-not (Test-Path $bak)) { Copy-Item $envFile $bak; Ok 'Copie de securite du .env : .env.backup' }
$changements = @{ 'APP_ENV' = 'production'; 'APP_DEBUG' = 'false'; 'APP_URL' = "http://$ip"; 'CARTE_URL' = "http://$ip" }
if ("$($cfg['SESSION_SECURE_COOKIE'])".ToLower() -eq 'true') { $changements['SESSION_SECURE_COOKIE'] = 'false' }
Modifier-Env $envFile $changements
Ok "Mode production, adresse http://$ip (APP_KEY inchangee)"

# ================================================================= 5
Titre '5/12  Extensions PHP'
$ini = Join-Path $PhpDir 'php.ini'
if (-not (Test-Path $ini)) {
    $modele = Join-Path $PhpDir 'php.ini-production'
    if (Test-Path $modele) { Copy-Item $modele $ini } else { Fin-Erreur "php.ini introuvable dans $PhpDir." }
}
$voulues = @('openssl', 'curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'exif', 'mysqli', 'pdo_mysql', 'zip', 'sodium')
$mods = @(& $Php -m) | ForEach-Object { "$_".Trim().ToLower() }
$texte = [IO.File]::ReadAllText($ini)
$avant = $texte
$nl = Fin-De-Ligne $texte
foreach ($e in $voulues) {
    if ($mods -contains $e) { continue }
    $re = '(?m)^[ \t]*;[ \t]*extension[ \t]*=[ \t]*"?(php_)?' + [regex]::Escape($e) + '(\.dll)?"?[ \t]*(?=\r?$)'
    if ([regex]::IsMatch($texte, $re)) {
        $texte = [regex]::Replace($texte, $re, "extension=$e")
    } elseif (Test-Path (Join-Path $PhpDir "ext\php_$e.dll")) {
        $texte = $texte.TrimEnd() + $nl + "extension=$e" + $nl
    }
}
if ($texte -ne $avant) {
    if (-not (Test-Path "$ini.avant-wesmile")) { Copy-Item $ini "$ini.avant-wesmile" }
    Ecrire-Texte $ini $texte
}
$mods = @(& $Php -m) | ForEach-Object { "$_".Trim().ToLower() }
$absentes = @($voulues | Where-Object { $mods -notcontains $_ })
if ($absentes.Count -gt 0) { Attention ('Extensions non chargees : ' + ($absentes -join ', ')) } else { Ok 'Extensions PHP actives' }

# ================================================================= 6
Titre '6/12  Composants PHP (Composer) - quelques minutes'
Set-Location $Projet
$env:COMPOSER_ALLOW_SUPERUSER = '1'
& composer install --no-dev --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'composer install a echoue (verifiez la connexion Internet).' }
Ok 'Composants PHP installes'

# ================================================================= 7
Titre '7/12  Configuration Apache et MySQL'
$httpdConf = Join-Path $Xampp 'apache\conf\httpd.conf'
$t = [IO.File]::ReadAllText($httpdConf)
$t2 = [regex]::Replace($t, '(?m)^[ \t]*#[ \t]*(Include[ \t]+"?conf/extra/httpd-vhosts\.conf"?)', '$1')
$t2 = [regex]::Replace($t2, '(?m)^[ \t]*#[ \t]*(LoadModule[ \t]+rewrite_module[ \t]+modules/mod_rewrite\.so)', '$1')
if ($t2 -ne $t) {
    if (-not (Test-Path "$httpdConf.avant-wesmile")) { Copy-Item $httpdConf "$httpdConf.avant-wesmile" }
    Ecrire-Texte $httpdConf $t2
}

$vhosts = Join-Path $Xampp 'apache\conf\extra\httpd-vhosts.conf'
$bloc = @'
# >>> WESMILE (bloc gere par le script d'installation - ne pas modifier)
<VirtualHost *:80>
    ServerName wesmile
    DocumentRoot "C:/wesmile/public"
    <Directory "C:/wesmile/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require ip 127.0.0.1 ::1 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16
    </Directory>
    ErrorLog "logs/wesmile-error.log"
    CustomLog "logs/wesmile-access.log" common
</VirtualHost>
# <<< WESMILE
'@
$vt = ''
if (Test-Path $vhosts) { $vt = [IO.File]::ReadAllText($vhosts) }
$nlv = Fin-De-Ligne $vt
$blocNl = ($bloc -split '\r?\n') -join $nlv
if ($vt -match '(?s)# >>> WESMILE.*?# <<< WESMILE') {
    $vt2 = [regex]::Replace($vt, '(?s)# >>> WESMILE.*?# <<< WESMILE', [System.Text.RegularExpressions.MatchEvaluator] { param($m) $blocNl })
} else {
    if (Test-Path $vhosts) { Copy-Item $vhosts "$vhosts.avant-wesmile" -Force }
    $vt2 = $vt.TrimEnd() + $nlv + $nlv + $blocNl + $nlv
}
Ecrire-Texte $vhosts $vt2

& (Join-Path $Xampp 'apache\bin\httpd.exe') -t
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'La configuration Apache contient une erreur (voir ci-dessus).' }
Ok 'Apache : seul le dossier C:\wesmile\public est publie'

$myIni = Join-Path $Xampp 'mysql\bin\my.ini'
if (Test-Path $myIni) {
    $mt = [IO.File]::ReadAllText($myIni)
    if ($mt -notmatch '(?m)^[ \t]*bind-address[ \t]*=') {
        if (-not (Test-Path "$myIni.avant-wesmile")) { Copy-Item $myIni "$myIni.avant-wesmile" }
        $nlm = Fin-De-Ligne $mt
        $reM = New-Object System.Text.RegularExpressions.Regex('(?m)^\[mysqld\][ \t]*(?=\r?$)')
        $mt2 = $reM.Replace($mt, ('[mysqld]' + $nlm + 'bind-address=127.0.0.1'), 1)
        if ($mt2 -ne $mt) { Ecrire-Texte $myIni $mt2; Ok 'MySQL : accessible seulement depuis le mini PC lui-meme' }
    } else { Ok 'MySQL : bind-address deja regle' }
}

# ================================================================= 8
Titre '8/12  Services Windows (demarrage automatique)'
if (-not (Get-Service -Name 'mysql' -ErrorAction SilentlyContinue)) {
    if (Get-Process -Name 'mysqld', 'mariadbd' -ErrorAction SilentlyContinue) {
        Attention 'MySQL est lance depuis le XAMPP Control Panel : cliquez sur Stop devant MySQL.'
        Read-Host '  Puis appuyez sur Entree'
    }
    $occ = Port-Occupe 3306
    if ($occ) { Fin-Erreur "Le port 3306 est deja utilise par '$occ'. Fermez ce programme puis relancez." }
    & $mysqld --install mysql "--defaults-file=$myIni"
    if ($LASTEXITCODE -ne 0) { Fin-Erreur 'Impossible d''installer le service MySQL.' }
}
if (-not (Get-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue)) {
    if (Get-Process -Name 'httpd' -ErrorAction SilentlyContinue) {
        Attention 'Apache est lance depuis le XAMPP Control Panel : cliquez sur Stop devant Apache.'
        Read-Host '  Puis appuyez sur Entree'
    }
    foreach ($p in 80, 443) {
        $occ = Port-Occupe $p
        if ($occ) { Fin-Erreur "Le port $p est deja utilise par '$occ'. Fermez ce programme (ou desinstallez IIS) puis relancez." }
    }
    & (Join-Path $Xampp 'apache\bin\httpd.exe') -k install -n 'Apache2.4'
    if ($LASTEXITCODE -ne 0) { Fin-Erreur 'Impossible d''installer le service Apache.' }
}
foreach ($s in 'mysql', 'Apache2.4') {
    & sc.exe config $s start= auto | Out-Null
    & sc.exe failure $s reset= 86400 actions= restart/60000/restart/60000/restart/300000 | Out-Null
}
if ((Get-Service -Name 'mysql').Status -ne 'Running') { Start-Service -Name 'mysql' -ErrorAction SilentlyContinue }

# Attendre que MySQL reponde (mot de passe root demande seulement s'il en a un)
$pret = $false
$motDePasseDemande = $false
for ($i = 0; $i -lt 40; $i++) {
    $sortie = & $mysqlCli -u root -e 'SELECT 1' 2>&1 | Out-String
    if ($LASTEXITCODE -eq 0) { $pret = $true; break }
    if ($sortie -match 'Access denied' -and -not $motDePasseDemande) {
        $motDePasseDemande = $true
        $sec = Read-Host '  Mot de passe du compte root de MySQL' -AsSecureString
        $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
        $env:MYSQL_PWD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)
        continue
    }
    Start-Sleep -Seconds 1
}
if (-not $pret) { Fin-Erreur 'MySQL ne demarre pas. Regardez le fichier .err dans C:\xampp\mysql\data.' }
Ok 'Service MySQL demarre (automatique, relance apres panne)'

if ((Get-Service -Name 'Apache2.4').Status -eq 'Running') { Restart-Service -Name 'Apache2.4' } else { Start-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue }
Start-Sleep -Seconds 2
if ((Get-Service -Name 'Apache2.4').Status -ne 'Running') { Fin-Erreur 'Apache ne demarre pas. Regardez C:\xampp\apache\logs\error.log.' }
Ok 'Service Apache demarre (automatique, relance apres panne)'

# ================================================================= 9
Titre '9/12  Base de donnees'
$baseOk = $false
$q = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db'"
$n = 0
try { $n = [int]("$(& $mysqlCli -u root -N -B -e $q 2>$null | Select-Object -First 1)".Trim()) } catch { $n = 0 }
if ($n -gt 0) {
    Ok "La base '$db' contient deja $n tables : import ignore (rien n'est ecrase)"
    $baseOk = $true
} else {
    $cands = @()
    if ($source) { $p1 = Parent-Sur $source; if ($p1) { $cands += (Join-Path $p1 'base-wesmile.sql') } }
    $p2 = Parent-Sur (Parent-Sur $PSScriptRoot)
    if ($p2) { $cands += (Join-Path $p2 'base-wesmile.sql') }
    foreach ($dr in (Get-PSDrive -PSProvider FileSystem)) { $cands += (Join-Path $dr.Root 'wesmile-transfert\base-wesmile.sql') }
    $sql = $cands | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
    if (-not $sql) {
        Attention 'Fichier base-wesmile.sql non trouve : choisissez la sauvegarde .sql de la base.'
        try {
            Add-Type -AssemblyName System.Windows.Forms
            $dlg = New-Object System.Windows.Forms.OpenFileDialog
            $dlg.Filter = 'Sauvegarde SQL (*.sql)|*.sql'
            $dlg.Title = 'Choisissez la sauvegarde de la base (.sql)'
            if ($dlg.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) { $sql = $dlg.FileName }
        } catch { }
    }
    if (-not $sql) {
        Attention 'Pas de sauvegarde : la base reste vide. Relancez ce script quand vous aurez le fichier .sql.'
    } else {
        Info "Import de $sql ..."
        New-Item -ItemType Directory -Force -Path $Temp | Out-Null
        $adm = New-Object System.Collections.Generic.List[string]
        $adm.Add('CREATE DATABASE IF NOT EXISTS `' + $db + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
        if ($dbUser -ne 'root') {
            $pw = $dbPass.Replace('\', '\\').Replace("'", "\'")
            $adm.Add("CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$pw';")
            $adm.Add("CREATE USER IF NOT EXISTS '$dbUser'@'127.0.0.1' IDENTIFIED BY '$pw';")
            $adm.Add(('GRANT ALL PRIVILEGES ON `{0}`.* TO ''{1}''@''localhost'';' -f $db, $dbUser))
            $adm.Add(('GRANT ALL PRIVILEGES ON `{0}`.* TO ''{1}''@''127.0.0.1'';' -f $db, $dbUser))
            $adm.Add('FLUSH PRIVILEGES;')
        }
        Ecrire-Texte (Join-Path $Temp 'admin.sql') ($adm -join "`n")
        & $mysqlCli -u root --default-character-set=utf8mb4 -e 'source C:/wesmile-temp/admin.sql'
        Remove-Item (Join-Path $Temp 'admin.sql') -Force -ErrorAction SilentlyContinue
        if ($LASTEXITCODE -ne 0) { Fin-Erreur 'Impossible de creer la base de donnees.' }
        Copy-Item -LiteralPath $sql -Destination (Join-Path $Temp 'base.sql') -Force
        & $mysqlCli -u root --default-character-set=utf8mb4 $db -e 'source C:/wesmile-temp/base.sql'
        $codeImport = $LASTEXITCODE
        Remove-Item -LiteralPath $Temp -Recurse -Force -ErrorAction SilentlyContinue
        if ($codeImport -ne 0) { Fin-Erreur 'L''import de la base a echoue (voir le message ci-dessus).' }
        try { $n = [int]("$(& $mysqlCli -u root -N -B -e $q 2>$null | Select-Object -First 1)".Trim()) } catch { $n = 0 }
        if ($n -lt 1) { Fin-Erreur 'La base est toujours vide apres l''import.' }
        Ok "Base importee ($n tables)"
        $baseOk = $true
    }
}
Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue

# ================================================================= 10
Titre '10/12  Pages de la plateforme (Node.js) - quelques minutes'
Set-Location $Projet
& npm.cmd ci
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'npm ci a echoue (verifiez la connexion Internet).' }
& npm.cmd run build
if ($LASTEXITCODE -ne 0) { Fin-Erreur 'npm run build a echoue.' }
Ok 'Pages preparees'

# ================================================================= 11
Titre '11/12  Finalisation Laravel'
if ($baseOk) {
    & $Php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { Fin-Erreur 'php artisan migrate a echoue (voir ci-dessus).' }
    Ok 'Base a jour'
} else {
    Attention 'Base vide : migration non lancee (relancez ce script avec la sauvegarde .sql).'
}
& $Php artisan storage:link | Out-Null
& $Php artisan optimize
if ($LASTEXITCODE -ne 0) {
    Attention 'optimize a echoue : la plateforme fonctionnera sans cache.'
    & $Php artisan optimize:clear | Out-Null
} else { Ok 'Cache Laravel prepare' }

# ================================================================= 12
Titre '12/12  Reglages Windows et taches automatiques'
$regle = 'Wesmile - site web (HTTP)'
if (-not (Get-NetFirewallRule -DisplayName $regle -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $regle -Direction Inbound -Action Allow -Protocol TCP -LocalPort 80 -Profile Private, Domain -RemoteAddress LocalSubnet | Out-Null
}
Get-NetConnectionProfile -ErrorAction SilentlyContinue | Where-Object { $_.NetworkCategory -eq 'Public' } | ForEach-Object {
    Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private -ErrorAction SilentlyContinue
}
Ok 'Pare-feu : port 80 ouvert pour le reseau local seulement'

Set-ItemProperty -Path 'HKLM:\System\CurrentControlSet\Control\Terminal Server' -Name 'fDenyTSConnections' -Value 0 -ErrorAction SilentlyContinue
Enable-NetFirewallRule -Group '@FirewallAPI.dll,-28752' -ErrorAction SilentlyContinue
Ok 'Bureau a distance active (le compte Windows doit avoir un mot de passe)'

& powercfg /change standby-timeout-ac 0 | Out-Null
& powercfg /change hibernate-timeout-ac 0 | Out-Null
& powercfg /change monitor-timeout-ac 15 | Out-Null
Ok 'Mise en veille desactivee (l''ecran peut s''eteindre)'

& schtasks.exe /Create /TN 'Wesmile\Planificateur' /SC MINUTE /MO 1 /RU SYSTEM /RL HIGHEST /F /TR "$Php $Projet\artisan schedule:run" | Out-Null
if ($LASTEXITCODE -eq 0) { Ok 'Rappels automatiques programmes (chaque minute)' } else { Attention 'Tache Planificateur non creee.' }

$scriptSauv = Join-Path $Projet 'deploiement\sauvegarde.ps1'
if (Test-Path $scriptSauv) {
    $act = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$scriptSauv`" -Silencieux"
    $dec = New-ScheduledTaskTrigger -Daily -At '20:30'
    $reg = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 3)
    $qui = New-ScheduledTaskPrincipal -UserId 'S-1-5-18' -LogonType ServiceAccount -RunLevel Highest
    try {
        Register-ScheduledTask -TaskName 'Sauvegarde' -TaskPath '\Wesmile\' -Action $act -Trigger $dec -Settings $reg -Principal $qui -Force -ErrorAction Stop | Out-Null
        Ok 'Sauvegarde automatique chaque soir a 20h30 (dans C:\Sauvegardes-Wesmile)'
    } catch { Attention "Tache de sauvegarde non creee : $($_.Exception.Message)" }
}

# ================================================================= Test
Titre 'Test final'
Restart-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3
$code = 0
try {
    $r = Invoke-WebRequest -Uri 'http://localhost/login' -UseBasicParsing -TimeoutSec 60
    $code = [int]$r.StatusCode
} catch {
    if ($_.Exception.Response) { $code = [int]$_.Exception.Response.StatusCode }
}
$mac = (Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' -and $_.Name -eq $ipObj.InterfaceAlias } | Select-Object -First 1).MacAddress

Write-Host ''
if ($code -eq 200) {
    Write-Host '  ==========================================' -ForegroundColor Green
    Write-Host '   INSTALLATION TERMINEE : LA PLATEFORME MARCHE' -ForegroundColor Green
    Write-Host '  ==========================================' -ForegroundColor Green
} else {
    Write-Host "  [!] La page de connexion ne repond pas correctement (code $code)." -ForegroundColor Yellow
    Info "Regardez $Projet\storage\logs\laravel.log et C:\xampp\apache\logs\wesmile-error.log"
}
Write-Host ''
Info "Plateforme (autres PC) :  http://$ip"
Info "Borne :                   http://$ip/borne"
Info "TV salle d'attente :      http://$ip/tv"
Info "Tablette fiche patient :  http://$ip/accueil"
Write-Host ''
Write-Host '  A faire maintenant :' -ForegroundColor Cyan
Info "1. Dans le routeur, reservez l'adresse $ip pour ce mini PC (adresse MAC : $mac)."
Info '2. Supprimez le dossier wesmile-transfert de la cle USB (donnees patients).'
Info '3. N''utilisez plus la plateforme sur le PC Dell : tout le monde passe sur cette adresse.'
Info '4. Ne lancez plus "php artisan serve" sur ce mini PC : Apache s''en occupe.'
Info "Journal de l'installation : $Journal"
try { Stop-Transcript | Out-Null } catch { }
Write-Host ''
Read-Host '  Appuyez sur Entree pour fermer'
