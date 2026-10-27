<?php

namespace App\Services;

use App\Enums\StatutRdv;
use App\Models\Acte;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Prise de rendez-vous commune : agenda de la réception, fin de séance du docteur, dossier. */
class Planificateur
{
    /**
     * @param  array{en_ligne?:bool, patient_id:int, docteur_id:int, acte_id?:int|null, mission?:string|null, date:string, heure:string, duree:int, forcer?:bool}  $d
     * @param  string  $cle  nom du champ qui reçoit l'erreur de créneau
     */
    public static function creer(array $d, ?User $par, string $cle = 'heure'): RendezVous
    {
        $debut = Carbon::parse($d['date'].' '.$d['heure']);
        $fin = $debut->copy()->addMinutes((int) $d['duree']);
        $docteur = User::findOrFail($d['docteur_id']);

        if (empty($d['forcer'])) {
            if (! self::disponible($docteur, $debut, $fin)) {
                throw ValidationException::withMessages([$cle => "{$docteur->name} n'est pas disponible à cette heure (voir ses horaires)."]);
            }
            if (self::conflit($docteur->id, $debut, $fin)) {
                throw ValidationException::withMessages([$cle => 'Ce créneau est déjà pris pour ce docteur.']);
            }
        }

        $acte = ! empty($d['acte_id']) ? Acte::find($d['acte_id']) : null;
        $rdv = RendezVous::create([
            'patient_id' => $d['patient_id'],
            'docteur_id' => $docteur->id,
            'acte_id' => $acte?->id,
            'mission' => ($d['mission'] ?? null) ?: ($acte?->libelle.($acte?->dents ? ' · '.$acte->dents : '')),
            'debut' => $debut, 'fin' => $fin,
            'statut' => StatutRdv::Planifie,
            'cree_par' => $par?->id,
            'en_ligne' => ! empty($d['en_ligne']),
        ]);

        // Si ce RDV est la pose d'un travail labo, on met à jour la date de pose.
        if ($acte) {
            $acte->dossier->commandesLabo()->where('acte_id', $acte->id)->whereNotIn('statut', ['pose'])
                ->update(['date_pose' => $debut->toDateString()]);
        }

        // Rien de prévu pour cette visite : « Consultation » (1re fois) ou « Contrôle » dans « À faire »
        if (! $acte && ($dossier = \App\Models\Dossier::where('patient_id', $rdv->patient_id)->first())) {
            $dossier->acteDeVisite($rdv, $par?->id);
        }

        return $rdv->setRelation('docteur', $docteur);
    }

    public static function disponible(User $docteur, Carbon $debut, Carbon $fin): bool
    {
        return $docteur->disponibilites()->where('jour_semaine', $debut->dayOfWeekIso)
            ->where('heure_debut', '<=', $debut->format('H:i:s'))->where('heure_fin', '>=', $fin->format('H:i:s'))->exists();
    }

    public static function conflit(int $docteurId, Carbon $debut, Carbon $fin, ?int $ignorer = null): bool
    {
        return RendezVous::where('docteur_id', $docteurId)->actifs()
            ->when($ignorer, fn ($q) => $q->whereKeyNot($ignorer))
            ->where('debut', '<', $fin)->where('fin', '>', $debut)->exists();
    }

    /** Créneaux libres d'un docteur pour un jour (toutes les 15 min). */
    public static function creneaux(User $docteur, Carbon $jour, int $duree): array
    {
        $plages = $docteur->disponibilites()->where('jour_semaine', $jour->dayOfWeekIso)->orderBy('heure_debut')->get();
        $pris = RendezVous::where('docteur_id', $docteur->id)->actifs()
            ->whereDate('debut', $jour->toDateString())->get(['debut', 'fin']);
        $libres = [];
        foreach ($plages as $p) {
            $t = Carbon::parse($jour->toDateString().' '.$p->heure_debut);
            $limite = Carbon::parse($jour->toDateString().' '.$p->heure_fin);
            while ($t->copy()->addMinutes($duree)->lte($limite)) {
                $f = $t->copy()->addMinutes($duree);
                $occupe = $pris->contains(fn ($r) => $r->debut->lt($f) && ($r->fin ?? $r->debut->copy()->addMinutes(30))->gt($t));
                if (! $occupe && $t->gt(now())) {
                    $libres[] = $t->format('H:i');
                }
                $t->addMinutes(15);
            }
        }

        return $libres;
    }
}
