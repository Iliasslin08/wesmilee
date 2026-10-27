<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importe les anciens patients depuis un fichier Excel (.xlsx) ou CSV
 * (modèle « modele-import-patients.xlsx », ou export Dental Evolution).
 * Les doublons (même téléphone, même nom + prénom, même CIN, même ancien n°) sont ignorés.
 */
class ImporterPatients extends Command
{
    protected $signature = 'cabinet:importer {fichier : chemin du fichier .xlsx ou .csv} {--essai : montrer le résultat sans rien enregistrer} {--force : ne pas demander de confirmation} {--numeros : garder le n° de dossier du fichier comme n° de dossier We Smile} {--completer : compléter les dossiers qui existent déjà (même n°) sans rien écraser}';

    protected $description = 'Importe les anciens patients depuis un fichier Excel ou CSV';

    /** Titre de colonne (simplifié) → champ. Le premier mot-clé trouvé gagne. */
    private const COLONNES = [
        'prenom' => 'prenom',
        'nom' => 'nom',
        'tel' => 'telephone', 'mobile' => 'telephone', 'gsm' => 'telephone', 'portable' => 'telephone',
        'naissance' => 'date_naissance', 'ne le' => 'date_naissance',
        'sexe' => 'sexe', 'genre' => 'sexe',
        'cin' => 'cin',
        'ville' => 'ville',
        'adresse' => 'adresse',
        'dossier' => 'ancien_numero', 'ref' => 'ancien_numero',
        'allerg' => 'allergies',
        'maladie' => 'antecedents', 'antecedent' => 'antecedents',
        'reste' => 'reste', 'solde' => 'reste',
        'remarque' => 'remarques', 'note' => 'remarques', 'observation' => 'remarques',
        'age' => 'age',
        'profession' => 'profession',
        'mail' => 'email',
        'plan' => 'plan',
        'fait' => 'faits',
        'a faire' => 'plan',
        'dent' => 'dents',
    ];

    public function handle(): int
    {
        $chemin = $this->argument('fichier');
        if (! is_file($chemin)) {
            $this->error("Fichier introuvable : $chemin");

            return self::FAILURE;
        }

        try {
            $lignes = str_ends_with(strtolower($chemin), '.csv') ? $this->lireCsv($chemin) : $this->lireXlsx($chemin);
        } catch (\Throwable $e) {
            $this->error('Impossible de lire le fichier : '.$e->getMessage());

            return self::FAILURE;
        }
        if (count($lignes) < 2) {
            $this->error('Le fichier est vide.');

            return self::FAILURE;
        }

        $champs = $this->reconnaitreColonnes(array_shift($lignes));
        if (! in_array('nom', $champs, true)) {
            $this->error('Colonne « Nom » introuvable dans la première ligne.');

            return self::FAILURE;
        }
        $this->line('Colonnes reconnues : '.collect($champs)->filter()->map(fn ($c, $i) => ($i + 1).'→'.$c)->implode(', '));

        $aCreer = $doublons = $erreurs = $aCompleter = [];
        $vus = [];
        foreach ($lignes as $n => $ligne) {
            $num = $n + 2; // n° de ligne dans Excel
            $d = [];
            $dernier = null; // dernier acte lu (pour la colonne « Dents » qui le suit)
            foreach ($champs as $i => $champ) {
                $v = trim((string) ($ligne[$i] ?? ''));
                if (in_array($champ, ['plan', 'faits'], true)) {
                    // Plusieurs colonnes « À faire 1, 2… » / « Fait 1, 2… » : un acte par colonne
                    $d[$champ] = trim(($d[$champ] ?? '')."\n".$v);
                    $dernier = $v !== '' ? $champ : null;
                } elseif ($champ === 'dents') {
                    if ($dernier && $v !== '') {
                        $d[$dernier] .= ' · '.$v;
                    }
                    $dernier = null;
                } elseif ($champ && ! isset($d[$champ])) {
                    $d[$champ] = $v;
                }
            }
            if (($d['nom'] ?? '') === '' && ($d['prenom'] ?? '') === '') {
                continue; // ligne vide
            }
            if (mb_strtoupper($d['nom'] ?? '') === 'EXEMPLE') {
                continue;
            }
            // « Nom » contient parfois « NOM Prénom »
            if (($d['prenom'] ?? '') === '' && str_contains($d['nom'], ' ')) {
                [$d['nom'], $d['prenom']] = explode(' ', $d['nom'], 2);
            }
            if (($d['nom'] ?? '') === '') {
                $erreurs[] = [$num, $d['prenom'] ?? '', 'nom manquant'];

                continue;
            }

            $p = [
                'nom' => mb_strtoupper(Str::squish($d['nom'])),
                'prenom' => Str::title(Str::squish($d['prenom'] ?? '')) ?: '-',
                'telephone' => $this->telephone($d['telephone'] ?? ''),
                'date_naissance' => $this->date($d['date_naissance'] ?? ''),
                'sexe' => $this->sexe($d['sexe'] ?? ''),
                'cin' => mb_strtoupper(str_replace(' ', '', $d['cin'] ?? '')) ?: null,
                'ville' => Str::title($d['ville'] ?? '') ?: null,
                'adresse' => ($d['adresse'] ?? '') ?: null,
                'allergies' => ($d['allergies'] ?? '') ?: null,
                'antecedents_autres' => ($d['antecedents'] ?? '') ?: null,
                'profession' => Str::limit(Str::squish($d['profession'] ?? ''), 100, '') ?: null,
                'email' => filter_var(trim($d['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null,
            ];
            // Plan de traitement du dossier papier : une ligne (ou « ; ») par acte
            $lignesActes = fn (string $t) => array_values(array_filter(array_map(fn ($x) => Str::squish($x), preg_split('/\r\n|\r|\n|;/', $t)), fn ($x) => $x !== '' && $x !== '-'));
            $actes = ['plan' => $lignesActes($d['plan'] ?? ''), 'faits' => $lignesActes($d['faits'] ?? '')];
            if (($d['date_naissance'] ?? '') !== '' && ! $p['date_naissance']) {
                $erreurs[] = [$num, $p['nom'].' '.$p['prenom'], 'date de naissance illisible ('.$d['date_naissance'].') : importé sans date'];
            }
            $ancien = ($d['ancien_numero'] ?? '') ?: null;
            // « 59 ans et 10 mois » : sans date de naissance, on l'estime (au mois près) pour que l'âge s'affiche
            $age = ($d['age'] ?? '') !== '' && preg_match('/^(\d+)\s*ans?(?:\D+(\d+)\s*mois)?/iu', trim($d['age']), $ma) ? (int) $ma[1] : null;
            $estimee = false;
            if ($age !== null && ($age < 1 || $age > 105)) {
                $erreurs[] = [$num, $p['nom'].' '.$p['prenom'], 'âge incohérent ('.$d['age'].') : importé sans âge'];
                $age = null;
            } elseif ($age !== null && ! $p['date_naissance']) {
                $p['date_naissance'] = now()->subYears($age)->subMonths((int) ($ma[2] ?? 0))->format('Y-m-d');
                $estimee = true;
            }
            $notes = array_filter([
                $estimee ? 'Date de naissance estimée d’après l’âge de l’ancien logiciel ('.trim($d['age']).' le '.now()->format('d/m/Y').') : à vérifier avec le patient' : null,
                ($d['reste'] ?? '') !== '' && (float) str_replace([' ', ','], ['', '.'], $d['reste']) > 0 ? 'Reste à payer (ancien logiciel) : '.$d['reste'].' DH' : null,
                ($d['remarques'] ?? '') ?: null,
            ]);

            $garder = $this->option('numeros') || $this->option('completer');
            if ($garder && ! $ancien) {
                $erreurs[] = [$num, $p['nom'].' '.$p['prenom'], 'n° de dossier manquant : ligne ignorée'];

                continue;
            }
            // doublon dans le fichier lui-même (avec --numeros : seulement le même n° de dossier)
            $cle = $garder ? 'n'.$ancien : $p['nom'].'|'.$p['prenom'].'|'.($p['telephone'] ?? '');
            if (isset($vus[$cle])) {
                $doublons[] = [$num, $p['nom'].' '.$p['prenom'], 'déjà à la ligne '.$vus[$cle].' du fichier'];

                continue;
            }
            $vus[$cle] = $num;

            // doublon dans la plateforme
            $raison = null;
            if ($garder) {
                $existant = Dossier::with('patient')->where('numero', $ancien)->first();
                if ($existant && $this->option('completer')) {
                    // Dossier déjà là (liste importée vide) : on le complète
                    $aCompleter[] = ['dossier' => $existant, 'patient' => $p, 'notes' => $notes ? implode("\n", $notes) : null, 'actes' => $actes];

                    continue;
                }
                if ($existant || Dossier::withTrashed()->where('numero', $ancien)->orWhere('ancien_numero_papier', $ancien)->exists()) {
                    $raison = 'dossier n° '.$ancien.' existe déjà';
                }
            } elseif ($ancien && Dossier::withTrashed()->where('ancien_numero_papier', $ancien)->exists()) {
                $raison = 'ancien n° '.$ancien.' déjà importé';
            } elseif (($exist = Patient::doublonsPossibles($p))->isNotEmpty()) {
                $e = $exist->first();
                $raison = implode(', ', $e['raisons']).' que '.$e['nom'].' '.$e['prenom'].' (dossier '.($e['dossier']['numero'] ?? '?').')';
            }
            if ($raison) {
                $doublons[] = [$num, $p['nom'].' '.$p['prenom'], $raison];

                continue;
            }

            $aCreer[] = ['patient' => $p, 'ancien' => $ancien, 'notes' => $notes ? implode("\n", $notes) : null, 'actes' => $actes];
        }

        $this->newLine();
        $this->info(count($aCreer).' patient(s) à ajouter');
        if ($this->option('completer')) {
            $this->info(count($aCompleter).' dossier(s) existant(s) à compléter');
        }
        $this->line(count($doublons).' doublon(s) ignoré(s)');
        $this->line(count($erreurs).' remarque(s)');

        $rapport = preg_replace('/\.(xlsx|csv)$/i', '', $chemin).'-rapport.csv';
        $f = fopen($rapport, 'w');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['Ligne Excel', 'Patient', 'Résultat'], ';');
        foreach ($doublons as $r) {
            fputcsv($f, [$r[0], $r[1], 'IGNORÉ : '.$r[2]], ';');
        }
        foreach ($erreurs as $r) {
            fputcsv($f, $r, ';');
        }
        fclose($f);
        $this->line("Détail des doublons et remarques : $rapport");

        if ($this->option('essai')) {
            $this->warn('Mode essai : rien n’a été enregistré.');

            return self::SUCCESS;
        }
        if (! $aCreer && ! $aCompleter) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Ajouter '.count($aCreer).' patient(s) et compléter '.count($aCompleter).' dossier(s) ?', true)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($aCreer) {
            $barre = $this->output->createProgressBar(count($aCreer));
            foreach ($aCreer as $a) {
                $patient = Patient::create($a['patient'] + [
                    'statut_fiche' => 'validee',
                    'valide_le' => now(),
                    // le patient complète sa fiche sur la tablette s'il manque la date de naissance ou le téléphone
                    'fiche_a_completer' => ! $a['patient']['date_naissance'] || ! $a['patient']['telephone'],
                ]);
                $dossier = Dossier::create(['patient_id' => $patient->id, 'ancien_numero_papier' => $a['ancien'], 'notes' => $a['notes'], 'statut' => 'en_suivi']
                    + ($this->option('numeros') || $this->option('completer') ? ['numero' => $a['ancien']] : []));
                $this->ajouterActes($dossier, $a['actes']);
                $barre->advance();
            }
            $barre->finish();
        });
        $completes = 0;
        DB::transaction(function () use ($aCompleter, &$completes) {
            foreach ($aCompleter as $a) {
                $patient = $a['dossier']->patient;
                if (! $patient) {
                    continue;
                }
                // Seulement les cases vides : on n'écrase jamais ce que l'équipe a déjà saisi
                $vides = collect($a['patient'])->except(['nom', 'prenom'])
                    ->filter(fn ($v, $k) => filled($v) && blank($patient->{$k}))->all();
                // Date de naissance estimée d'après l'âge : remplacée par la vraie date
                $estimation = '/^Date de naissance estimée[^\n]*\n?/mu';
                if (filled($a['patient']['date_naissance']) && preg_match($estimation, (string) $a['dossier']->notes)) {
                    $vides['date_naissance'] = $a['patient']['date_naissance'];
                    $a['dossier']->update(['notes' => trim(preg_replace($estimation, '', (string) $a['dossier']->notes)) ?: null]);
                }
                if (($patient->prenom === '-' || blank($patient->prenom)) && filled($a['patient']['prenom']) && $a['patient']['prenom'] !== '-') {
                    $vides['prenom'] = $a['patient']['prenom'];
                }
                if ($vides) {
                    $patient->fill($vides);
                    $patient->fiche_a_completer = ! $patient->date_naissance || ! $patient->telephone;
                    $patient->save();
                }
                if ($a['notes'] && ! str_contains((string) $a['dossier']->notes, $a['notes'])) {
                    $a['dossier']->update(['notes' => trim($a['dossier']->notes."\n".$a['notes'])]);
                }
                $nActes = $this->ajouterActes($a['dossier'], $a['actes']);
                $completes += $vides || $a['notes'] || $nActes ? 1 : 0;
            }
        });
        $this->newLine(2);
        $this->info(count($aCreer).' patients ajoutés.'.($this->option('completer') ? " $completes dossier(s) complété(s)." : ''));

        return self::SUCCESS;
    }

    /** Actes du dossier papier : « plan » → À faire, « faits » → Fait (sans prix, déjà réglés sur papier). */
    private function ajouterActes(Dossier $dossier, array $actes): int
    {
        $n = 0;
        foreach ($actes['plan'] ?? [] as $texte) {
            $dossier->acteDemande($texte, null, null);
            $n++;
        }
        foreach ($actes['faits'] ?? [] as $texte) {
            $libelle = $texte;
            $dents = null;
            if (preg_match('/^(.+)\s+·\s+([^·]+)$/u', $texte, $m)) {
                [$libelle, $dents] = [trim($m[1]), trim($m[2])];
            }
            $existe = $dossier->actes()->where('statut', \App\Enums\StatutActe::Fait->value)->where('libelle', $libelle)->where('notes', 'like', 'Ancien dossier papier%')->exists();
            if (! $existe) {
                $dossier->actes()->create([
                    'libelle' => $libelle, 'dents' => $dents, 'prix_convenu' => 0, 'statut' => \App\Enums\StatutActe::Fait,
                    'notes' => 'Ancien dossier papier', 'fait_le' => null,
                ]);
                $n++;
            }
        }

        return $n;
    }

    /* ---------------- lecture ---------------- */

    private function reconnaitreColonnes(array $titres): array
    {
        return array_map(function ($t) {
            $t = Str::of((string) $t)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]/', ' ')->squish()->toString();
            if ($t === '') {
                return null;
            }
            // « N° dossier », « Ref dossier » avant « nom » (au cas où)
            foreach (self::COLONNES as $mot => $champ) {
                if ($mot === 'nom' ? (bool) preg_match('/^nom\b/', $t) : str_contains($t, $mot)) {
                    return $champ;
                }
            }

            return null;
        }, $titres);
    }

    private function lireCsv(string $chemin): array
    {
        $contenu = file_get_contents($chemin);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $premiere = strtok($contenu, "\n");
        $sep = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';
        $lignes = [];
        $h = fopen('php://memory', 'r+');
        fwrite($h, $contenu);
        rewind($h);
        while (($l = fgetcsv($h, 0, $sep)) !== false) {
            $lignes[] = $l;
        }

        return $lignes;
    }

    /** Lecteur .xlsx minimal (première feuille). */
    private function lireXlsx(string $chemin): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($chemin) !== true) {
            throw new \RuntimeException('ce n’est pas un fichier Excel .xlsx (enregistrez-le en .xlsx ou .csv)');
        }
        $partages = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si as $si) {
                $partages[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }
        // première feuille du classeur
        $feuille = 'xl/worksheets/sheet1.xml';
        $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $rid = (string) $wb->sheets->sheet[0]->attributes('r', true)->id;
        foreach ($rels->Relationship as $r) {
            if ((string) $r['Id'] === $rid) {
                $cible = ltrim((string) $r['Target'], '/');
                $feuille = str_starts_with($cible, 'xl/') ? $cible : 'xl/'.$cible;
            }
        }
        $styles = $this->stylesDate($zip);
        $sx = simplexml_load_string($zip->getFromName($feuille));
        $zip->close();

        $lignes = [];
        foreach ($sx->sheetData->row as $row) {
            $l = [];
            foreach ($row->c as $c) {
                $col = $this->colonne((string) $c['r']);
                $t = (string) $c['t'];
                $v = (string) $c->v;
                if ($t === 's') {
                    $v = $partages[(int) $v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = (string) $c->is->t;
                } elseif ($t === '' && $v !== '' && is_numeric($v) && in_array((int) $c['s'], $styles, true)) {
                    $v = Carbon::create(1899, 12, 30)->addDays((int) $v)->format('d/m/Y');
                }
                $l[$col] = $v;
            }
            if ($l) {
                $max = max(array_keys($l));
                $lignes[] = array_replace(array_fill(0, $max + 1, ''), $l);
            }
        }

        return $lignes;
    }

    /** Index des styles de cellule qui sont des dates. */
    private function stylesDate(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }
        $sx = simplexml_load_string($xml);
        $formatsDate = [14, 15, 16, 17, 22];
        foreach ($sx->numFmts->numFmt ?? [] as $f) {
            if (preg_match('/[dy]/i', preg_replace('/\[[^\]]*\]|"[^"]*"/', '', (string) $f['formatCode']))) {
                $formatsDate[] = (int) $f['numFmtId'];
            }
        }
        $res = [];
        $i = 0;
        foreach ($sx->cellXfs->xf ?? [] as $xf) {
            if (in_array((int) $xf['numFmtId'], $formatsDate, true)) {
                $res[] = $i;
            }
            $i++;
        }

        return $res;
    }

    private function colonne(string $ref): int
    {
        $lettres = preg_replace('/\d/', '', $ref);
        $n = 0;
        foreach (str_split($lettres) as $c) {
            $n = $n * 26 + (ord($c) - 64);
        }

        return $n - 1;
    }

    /* ---------------- nettoyage ---------------- */

    private function telephone(string $t): ?string
    {
        $c = preg_replace('/\D/', '', $t);
        if ($c === '') {
            return null;
        }
        if (str_starts_with($c, '212') && strlen($c) === 12) {
            $c = '0'.substr($c, 3);
        }
        if (strlen($c) === 9 && in_array($c[0], ['5', '6', '7'], true)) {
            $c = '0'.$c; // Excel a enlevé le 0
        }

        return substr($c, 0, 20);
    }

    private function date(string $t): ?string
    {
        $t = trim($t);
        if ($t === '') {
            return null;
        }
        $d = null;
        if (is_numeric($t) && (int) $t > 1000 && (int) $t < 80000) {
            $d = Carbon::create(1899, 12, 30)->addDays((int) $t);
        } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})\b#', $t, $m)) {
            $an = strlen($m[3]) === 2 ? ((int) $m[3] > (int) now()->format('y') ? 1900 : 2000) + (int) $m[3] : (int) $m[3];
            $d = checkdate((int) $m[2], (int) $m[1], $an) ? Carbon::create($an, (int) $m[2], (int) $m[1]) : null;
        } elseif (preg_match('#^(\d{4})[/.\-](\d{1,2})[/.\-](\d{1,2})\b#', $t, $m)) {
            $d = checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]) : null;
        }
        if (! $d || $d->year < 1900 || $d->isFuture()) {
            return null;
        }

        return $d->toDateString();
    }

    private function sexe(string $t): ?string
    {
        $t = mb_strtolower(trim($t));

        return match (true) {
            $t === '' => null,
            in_array($t[0], ['h', 'm'], true) => 'H',
            $t[0] === 'f' => 'F',
            default => null,
        };
    }
}
