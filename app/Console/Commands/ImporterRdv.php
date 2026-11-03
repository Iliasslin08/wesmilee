<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\StatutRdv;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reprend les rendez-vous de l'ancien logiciel (liste « Les rendez-vous du jour »).
 * Fichier CSV séparé par « ; » : patient;acte;docteur;date;heure;duree
 * Chaque RDV est relié au patient (trouvé par son nom) et à un acte « À faire » de son dossier.
 *
 *   php artisan cabinet:importer-rdv import/rdv-2026-11-03.csv --essai   (vérifier sans rien enregistrer)
 *   php artisan cabinet:importer-rdv import/rdv-2026-11-03.csv           (enregistrer)
 * Patient introuvable : une fiche minimale est créée, marquée « N° de dossier papier inconnu »
 * (alerte dans son dossier, à compléter à l'accueil).
 */
class ImporterRdv extends Command
{
    protected $signature = 'cabinet:importer-rdv {fichier} {--essai}';

    protected $description = "Importe les rendez-vous de l'ancien logiciel (CSV) et les relie aux patients et aux actes";

    public function handle(): int
    {
        $fichier = $this->argument('fichier');
        if (! is_file($fichier) && is_file(base_path($fichier))) {
            $fichier = base_path($fichier);
        }
        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : $fichier");

            return self::FAILURE;
        }

        $lignes = array_values(array_filter(array_map('trim', file($fichier))));
        array_shift($lignes); // en-tête
        $essai = (bool) $this->option('essai');
        $par = User::whereIn('role', [Role::Admin->value, Role::Developpeur->value])->orderBy('id')->first();

        // Index des patients : nom + prénom normalisés, dans les deux sens, avec et sans espaces
        $index = [];
        foreach (Patient::where('statut_fiche', 'validee')->get(['id', 'nom', 'prenom']) as $p) {
            foreach ($this->cles($p->nom.' '.$p->prenom) as $k) {
                $index[$k][$p->id] = $p->id;
            }
        }
        $docteurs = User::docteurs()->get(['id', 'name']);

        $ok = $deja = 0;
        $introuvables = $ambigus = $docInconnus = [];
        $tableau = [];

        foreach ($lignes as $n => $ligne) {
            [$nom, $acte, $doc, $date, $heure, $duree] = array_pad(array_map('trim', str_getcsv($ligne, ';')), 6, '');
            $docteur = $this->docteur($doc, $docteurs);
            if (! $docteur) {
                $docInconnus[$doc] = true;
                $tableau[] = [$heure, $nom, $acte, $doc, 'Docteur inconnu'];

                continue;
            }

            $ids = $this->trouver($nom, $index);
            if (count($ids) > 1) {
                $ambigus[] = $nom;
                $tableau[] = [$heure, $nom, $acte, $docteur->name, 'Plusieurs patients : à faire à la main'];

                continue;
            }
            $patient = $ids ? Patient::find(reset($ids)) : null;
            if (! $patient) {
                $introuvables[$nom] = true;
            }

            $debut = Carbon::parse("$date $heure");
            if ($patient && RendezVous::where('patient_id', $patient->id)->where('debut', $debut)->exists()) {
                $deja++;
                $tableau[] = [$heure, $patient->nom_complet, $acte, $docteur->name, 'Déjà dans l’agenda'];

                continue;
            }

            $tableau[] = [$heure, $patient?->nom_complet ?? $nom, $acte, $docteur->name, ($patient ? '' : 'Nouveau patient · PAS DE N° DOSSIER · ').($essai ? 'OK (essai)' : 'Ajouté')];
            $ok++;
            if ($essai) {
                continue;
            }

            DB::transaction(function () use (&$patient, $nom, $acte, $docteur, $debut, $duree, $par) {
                if (! $patient) {
                    $patient = $this->nouveauPatient($nom, $par, $debut);
                }
                $dossier = Dossier::firstOrCreate(['patient_id' => $patient->id], ['cree_par' => $par?->id]);
                // l'acte va dans « À faire » (ou reprend le même acte déjà prévu)
                $a = $dossier->acteDemande(Str::of($acte)->squish()->ucfirst()->value(), null, $docteur->id);
                RendezVous::create([
                    'patient_id' => $patient->id,
                    'docteur_id' => $docteur->id,
                    'acte_id' => $a->id,
                    'mission' => $a->libelle,
                    'debut' => $debut,
                    'fin' => $debut->copy()->addMinutes(max(10, (int) $duree)),
                    'statut' => StatutRdv::Planifie,
                    'cree_par' => $par?->id,
                ]);
                if ($dossier->statut === 'en_suivi') {
                    $dossier->update(['statut' => 'en_traitement']);
                }
            });
        }

        $this->table(['Heure', 'Patient', 'Acte', 'Docteur', 'Résultat'], $tableau);
        $this->info(($essai ? 'Essai : ' : '')."$ok rendez-vous ".($essai ? 'à ajouter' : 'ajoutés').", $deja déjà présents.");
        if ($introuvables) {
            $this->warn(count($introuvables).' patient(s) pas dans la base, PAS DE N° DE DOSSIER : '.implode(', ', array_keys($introuvables)));
            $this->line($essai
                ? '→ à l’import, une fiche minimale sera créée pour eux, avec l’alerte « N° de dossier papier inconnu ».'
                : '→ fiche minimale créée, avec l’alerte « N° de dossier papier inconnu » : à compléter à l’accueil (fiche + ancien n° papier).');
        }
        if ($ambigus) {
            $this->warn('Plusieurs patients possibles (à ajouter à la main) : '.implode(', ', $ambigus));
        }
        if ($docInconnus) {
            $this->warn('Docteur inconnu dans la plateforme : '.implode(', ', array_keys($docInconnus)));
        }

        return self::SUCCESS;
    }

    /** Mots en minuscules sans accents ni civilité (MR, MME…). */
    private function mots(string $s): array
    {
        $s = Str::lower(Str::ascii($s));
        $s = preg_replace('/[^a-z ]+/', ' ', $s);
        $mots = array_values(array_filter(explode(' ', $s)));
        while ($mots && in_array($mots[0], ['mr', 'mme', 'mlle', 'm', 'melle', 'dr'], true)) {
            array_shift($mots);
        }

        return $mots;
    }

    private function cles(string $nomComplet): array
    {
        $m = $this->mots($nomComplet);
        if (! $m) {
            return [];
        }
        $tri = $m;
        sort($tri);

        return array_unique(['t:'.implode(' ', $tri), 'c:'.implode('', $tri)]);
    }

    /** Patients qui correspondent au nom de l'ancien logiciel. */
    private function trouver(string $nom, array $index): array
    {
        foreach ($this->cles($nom) as $k) {
            if (! empty($index[$k])) {
                return $index[$k];
            }
        }

        return [];
    }

    private function docteur(string $nom, $docteurs): ?User
    {
        $cherche = array_filter($this->mots($nom), fn ($m) => strlen($m) >= 3);
        if (! $cherche) {
            return null;
        }
        $trouves = $docteurs->filter(function ($d) use ($cherche) {
            $mots = $this->mots($d->name);
            foreach ($cherche as $c) {
                $proche = collect($mots)->contains(fn ($m) => $m === $c || (strlen($m) >= 4 && levenshtein($m, $c) <= 2));
                if (! $proche) {
                    return false;
                }
            }

            return true;
        });

        return $trouves->count() === 1 ? $trouves->first() : null;
    }

    private function nouveauPatient(string $nom, ?User $par, Carbon $debut): Patient
    {
        $mots = preg_split('/\s+/', trim(preg_replace('/^(MR|MME|MLLE|M\.?)\s+/i', '', $nom)));
        $prenom = count($mots) > 1 ? array_pop($mots) : '';

        $p = Patient::create([
            'nom' => mb_strtoupper(implode(' ', $mots)),
            'prenom' => Str::title(Str::lower($prenom)),
            'fiche_a_completer' => true,
            'statut_fiche' => 'validee',
            'valide_par' => $par?->id,
            'valide_le' => now(),
        ]);
        Dossier::create([
            'patient_id' => $p->id,
            'cree_par' => $par?->id,
            'notes' => Dossier::SANS_NUMERO.' : patient repris de l’agenda de l’ancien logiciel (RDV du '.$debut->format('d/m/Y').'), introuvable dans la base.',
        ]);

        return $p;
    }
}
