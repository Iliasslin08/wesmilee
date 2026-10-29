<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Dossier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importe les scans des dossiers papier : un sous-dossier par patient, nommé avec le n° de dossier
 * (ex. « Dossier patient\5500\img20261028.jpg »). Chaque fichier va dans l'onglet « Dossier papier »
 * du bon patient. Les photos trop grandes sont réduites. Un fichier déjà importé n'est jamais recopié.
 */
class ImporterScans extends Command
{
    protected $signature = 'cabinet:importer-scans {dossier : chemin du dossier qui contient les sous-dossiers 5500, 5501…} {--essai : montrer sans rien enregistrer}';

    protected $description = 'Ajoute les scans des dossiers papier (un sous-dossier par n° de dossier) dans la plateforme';

    public function handle(): int
    {
        $racine = rtrim($this->argument('dossier'), '\\/');
        if (! is_dir($racine)) {
            $this->error("Dossier introuvable : $racine");

            return self::FAILURE;
        }
        $reduire = function_exists('imagecreatefromjpeg');
        if (! $reduire) {
            $this->warn('Extension GD absente : les photos seront copiées sans être réduites.');
        }

        $auteur = \App\Models\User::where('role', 'admin')->value('id') ?? \App\Models\User::value('id');
        $ajoutes = $deja = $introuvables = 0;
        $octetsAvant = $octetsApres = 0;
        $rapport = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        $dossiers = [];
        foreach ($iter as $f) {
            if ($f->isDir() && ctype_digit($f->getFilename())) {
                $dossiers[$f->getFilename()] = $f->getPathname();
            }
        }
        ksort($dossiers, SORT_NATURAL);

        foreach ($dossiers as $numero => $chemin) {
            $dossier = Dossier::with('patient')->where('numero', $numero)->first();
            $fichiers = collect(scandir($chemin))->filter(fn ($n) => preg_match('/\.(jpe?g|png|webp|pdf)$/i', $n))->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values();
            if (! $dossier?->patient) {
                $introuvables++;
                $rapport[] = [$numero, '—', count($fichiers).' fichier(s)', 'DOSSIER INTROUVABLE'];

                continue;
            }
            $patient = $dossier->patient;
            $n = 0;
            foreach ($fichiers as $nom) {
                $existe = $patient->documents()->where('type', 'ancien_dossier')->where('nom_original', $nom)->exists();
                if ($existe) {
                    $deja++;

                    continue;
                }
                $source = $chemin.DIRECTORY_SEPARATOR.$nom;
                $octetsAvant += filesize($source);
                if ($this->option('essai')) {
                    $n++;

                    continue;
                }
                $contenu = $reduire ? \App\Support\Photo::reduire($source) : null;
                $contenu ??= file_get_contents($source);
                $ext = $contenu !== false && $reduire && preg_match('/\.(jpe?g|png|webp)$/i', $nom) ? 'jpg' : strtolower(pathinfo($nom, PATHINFO_EXTENSION));
                $stockage = "patients/{$patient->id}/".Str::random(40).'.'.$ext;
                Storage::disk('local')->put($stockage, $contenu);
                $octetsApres += strlen($contenu);
                $patient->documents()->create(['type' => 'ancien_dossier', 'chemin' => $stockage, 'nom_original' => $nom, 'ajoute_par' => $auteur]);
                $n++;
            }
            $ajoutes += $n;
            $rapport[] = [$numero, $patient->nom_complet, count($fichiers).' fichier(s)', $n ? "$n ajouté(s)" : 'déjà fait'];
        }

        $this->table(['N° dossier', 'Patient', 'Scans', 'Résultat'], $rapport);
        $this->info("$ajoutes page(s) ajoutée(s) · $deja déjà présente(s) · $introuvables dossier(s) introuvable(s).");
        if (! $this->option('essai') && $octetsAvant > 0) {
            $this->line(sprintf('Taille : %.1f Mo → %.1f Mo', $octetsAvant / 1048576, $octetsApres / 1048576));
        }
        if ($this->option('essai')) {
            $this->warn('Mode essai : rien n’a été enregistré.');
        }

        return self::SUCCESS;
    }

}
