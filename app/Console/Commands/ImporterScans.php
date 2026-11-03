<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\User;
use App\Support\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ajoute les scans des anciens dossiers papier au dossier de chaque patient.
 * Un sous-dossier par patient, nommé avec le n° de dossier : « Dossier patient\5500\img….jpg ».
 */
class ImporterScans extends Command
{
    protected $signature = 'cabinet:importer-scans {dossier : chemin du dossier qui contient un sous-dossier par n° de dossier} {--essai : montrer le résultat sans rien enregistrer}';

    protected $description = 'Ajoute les photos des anciens dossiers papier (type « Ancien dossier papier ») à chaque patient';

    public function handle(): int
    {
        $racine = rtrim($this->argument('dossier'), '\\/');
        if (! File::isDirectory($racine)) {
            $this->error("Dossier introuvable : $racine");

            return self::FAILURE;
        }

        $essai = (bool) $this->option('essai');
        $auteur = User::where('role', 'admin')->value('id') ?? User::value('id');
        $ajoutes = 0;
        $dejaLa = 0;
        $introuvables = [];

        $fichiers = collect(File::allFiles($racine))
            ->filter(fn ($f) => in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'pdf']))
            ->sortBy(fn ($f) => $f->getPathname());

        foreach ($fichiers as $f) {
            // n° de dossier = nom du dossier qui contient l'image (ex. 5500, ou 5530 rangé dans 5529)
            $numero = basename($f->getPath());
            if (! ctype_digit($numero)) {
                $introuvables[$numero.' (nom non numérique)'] = true;

                continue;
            }

            $dossier = Dossier::with('patient')->where('numero', $numero)->first()
                ?? Dossier::with('patient')->where('ancien_numero_papier', $numero)->first();
            if (! $dossier || ! $dossier->patient) {
                $introuvables[$numero] = true;

                continue;
            }

            $nom = $numero.'/'.$f->getFilename();
            $patient = $dossier->patient;
            if ($patient->documents()->where('type', 'ancien_dossier')->where('nom_original', $nom)->exists()) {
                $dejaLa++;

                continue;
            }

            if (! $essai) {
                $estPdf = strtolower($f->getExtension()) === 'pdf';
                $reduite = $estPdf ? null : Photo::reduire($f->getPathname());
                $chemin = "patients/{$patient->id}/".Str::random(40).($reduite !== null ? '.jpg' : '.'.strtolower($f->getExtension()));
                Storage::disk('local')->put($chemin, $reduite ?? File::get($f->getPathname()));
                $patient->documents()->create([
                    'type' => 'ancien_dossier',
                    'chemin' => $chemin,
                    'nom_original' => $nom,
                    'ajoute_par' => $auteur,
                ]);
            }
            $ajoutes++;
            $this->line("  + dossier $numero · {$patient->nom_complet} · {$f->getFilename()}");
        }

        $this->newLine();
        $this->info(($essai ? '[ESSAI] ' : '')."$ajoutes image(s) ajoutée(s), $dejaLa déjà présente(s).");
        if ($introuvables) {
            $this->warn('Aucun dossier dans la plateforme pour : '.implode(', ', array_keys($introuvables)));
        }

        return self::SUCCESS;
    }
}
