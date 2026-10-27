<?php

namespace App\Console\Commands;

use App\Enums\StatutRdv;
use App\Models\RendezVous;
use App\Services\Sms;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Envoie un SMS de rappel à chaque patient qui a un rendez-vous demain (une seule fois par RDV). */
class EnvoyerRappels extends Command
{
    protected $signature = 'cabinet:rappels {--date= : jour des RDV (par défaut : demain)} {--essai : montrer les SMS sans les envoyer}';

    protected $description = 'Envoie les rappels SMS des rendez-vous du lendemain';

    public function handle(): int
    {
        if (! config('sms.actif')) {
            $this->warn('Rappels SMS désactivés (SMS_RAPPELS_ACTIFS=false).');

            return self::SUCCESS;
        }
        $jour = $this->option('date') ? Carbon::parse($this->option('date')) : now()->addDay();
        $rdvs = RendezVous::with('patient:id,prenom,nom,telephone', 'docteur:id,name')
            ->whereDate('debut', $jour->toDateString())
            ->where('statut', StatutRdv::Planifie->value)
            ->whereNull('rappel_envoye_le')
            ->orderBy('debut')->get();

        $envoyes = $sans = $echecs = 0;
        foreach ($rdvs as $rdv) {
            $numero = Sms::numero($rdv->patient?->telephone);
            if (! $numero) {
                $sans++;

                continue;
            }
            $message = self::message($rdv);
            if ($this->option('essai')) {
                $this->line("$numero : $message");

                continue;
            }
            [$ok, $statut, $erreur] = Sms::envoyer($numero, $message);
            DB::table('sms')->insert([
                'rendez_vous_id' => $rdv->id, 'patient_id' => $rdv->patient_id, 'telephone' => $numero, 'message' => $message,
                'statut' => $statut, 'erreur' => $erreur, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($ok) {
                $rdv->updateQuietly(['rappel_envoye_le' => now()]);
                $envoyes++;
            } else {
                $echecs++;
                $this->error("Échec pour $numero : $erreur");
            }
        }
        $this->info("Rappels du {$jour->format('d/m/Y')} : $envoyes envoyé(s), $echecs échec(s), $sans patient(s) sans numéro de mobile valide.");

        return $echecs ? self::FAILURE : self::SUCCESS;
    }

    public static function message(RendezVous $rdv): string
    {
        $sansAccents = fn ($t) => iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $t) ?: (string) $t;

        return $sansAccents(strtr(config('sms.message'), [
            ':prenom' => $rdv->patient?->prenom && $rdv->patient->prenom !== '-' ? $rdv->patient->prenom : ($rdv->patient?->nom ?? ''),
            ':cabinet' => config('cabinet.nom'),
            ':date' => $rdv->debut->format('d/m'),
            ':heure' => $rdv->debut->format('H:i'),
            ':docteur' => $rdv->docteur?->name ?? 'votre docteur',
            ':telephone' => str_replace(' ', '', (string) config('cabinet.telephone')),
        ]));
    }
}
