<?php

namespace App\Console\Commands;

use App\Enums\StatutActe;
use App\Models\Acte;
use App\Models\Dossier;
use App\Services\Messagerie;
use Illuminate\Console\Command;

/**
 * Rappels de suivi envoyés une seule fois (Mon espace + SMS si accepté) :
 * - contrôle prévu dans le dossier (dans les 7 jours) ;
 * - implant posé : contrôle à 3 mois, puis chaque année.
 */
class EnvoyerMessagesSuivi extends Command
{
    protected $signature = 'cabinet:messages-suivi {--essai : affiche sans envoyer}';

    protected $description = 'Envoie aux patients les rappels de contrôle et de suivi des implants';

    public function handle(): int
    {
        $tel = config('cabinet.telephone');
        $appel = $tel ? " Appelez-nous au $tel ou réservez dans Mon espace." : ' Réservez dans Mon espace.';
        $n = 0;

        // Contrôle prévu
        $dossiers = Dossier::with('patient')->whereNotNull('prochain_controle')
            ->whereBetween('prochain_controle', [today(), today()->addDays(7)])->get();
        foreach ($dossiers as $d) {
            if (! $d->patient) {
                continue;
            }
            $n += $this->envoyer($d->patient, 'controle', 'Contrôle à prévoir',
                'Votre contrôle dentaire est prévu vers le '.$d->prochain_controle->format('d/m/Y').'.'.$appel,
                'controle-'.$d->prochain_controle->toDateString());
        }

        // Implants : 3 mois après la pose, puis chaque année
        $implants = Acte::with('dossier.patient')->where('statut', StatutActe::Fait->value)
            ->where('libelle', 'like', '%implant%')->whereNotNull('fait_le')->get();
        foreach ($implants as $a) {
            $p = $a->dossier?->patient;
            if (! $p) {
                continue;
            }
            $mois = (int) $a->fait_le->diffInMonths(today());
            $dents = $a->dents ? ' ('.$a->dents.')' : '';
            if ($mois >= 3 && $mois < 4) {
                $n += $this->envoyer($p, 'implant', 'Contrôle de votre implant',
                    "Cela fait 3 mois que votre implant$dents a été posé : un contrôle est conseillé.$appel", 'implant-'.$a->id.'-3m');
            } elseif ($mois >= 12 && $mois % 12 === 0) {
                $ans = intdiv($mois, 12);
                $n += $this->envoyer($p, 'implant', 'Contrôle annuel de votre implant',
                    "Votre implant$dents a $ans an(s) : pensez au contrôle annuel.$appel", 'implant-'.$a->id.'-'.$ans.'a');
            }
        }

        $this->info(($this->option('essai') ? 'Essai : ' : '')."$n message(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function envoyer($patient, string $type, string $titre, string $texte, string $cle): int
    {
        if ($this->option('essai')) {
            $this->line("{$patient->nom_complet} · $titre");

            return 1;
        }

        return Messagerie::envoyer($patient, $type, $titre, $texte, null, $cle) ? 1 : 0;
    }
}
