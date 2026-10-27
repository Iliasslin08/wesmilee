<?php

namespace App\Console\Commands;

use App\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Enlève « MR » / « MME » / « MLLE » devant le nom (ancien logiciel), en déduit le sexe, et met le prénom en forme normale (Rajaa). */
class NettoyerNoms extends Command
{
    protected $signature = 'cabinet:nettoyer-noms {--essai : montrer sans modifier}';

    protected $description = 'Supprime MR/MME devant les noms des patients et corrige la casse des prénoms';

    public function handle(): int
    {
        $n = 0;
        Patient::query()->orderBy('id')->chunkById(500, function ($patients) use (&$n) {
            foreach ($patients as $p) {
                $nom = $p->nom;
                $sexe = $p->sexe;
                if (preg_match('/^\s*(MR|MME|MLLE)\.?\s+(.+)$/iu', $nom, $m)) {
                    $nom = $m[2];
                    $sexe = $sexe ?: (strtoupper($m[1]) === 'MR' ? 'H' : 'F');
                }
                $nom = mb_strtoupper(Str::squish($nom));
                $prenom = $p->prenom && $p->prenom !== '-' ? Str::title(mb_strtolower(Str::squish($p->prenom))) : $p->prenom;
                if ($nom !== $p->nom || $prenom !== $p->prenom || $sexe !== $p->sexe) {
                    $n++;
                    if ($n <= 10) {
                        $this->line("{$p->nom} {$p->prenom}  →  {$nom} {$prenom}".($sexe ? " ($sexe)" : ''));
                    }
                    if (! $this->option('essai')) {
                        $p->updateQuietly(['nom' => $nom, 'prenom' => $prenom, 'sexe' => $sexe]);
                    }
                }
            }
        });
        $this->info($this->option('essai') ? "$n patient(s) seraient corrigés (essai, rien n'est modifié)." : "$n patient(s) corrigés.");

        return self::SUCCESS;
    }
}
