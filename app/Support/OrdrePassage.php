<?php

namespace App\Support;

use App\Enums\StatutRdv;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ordre de passage « intelligent » de la salle d'attente.
 * Désactivable dans .env : ORDRE_INTELLIGENT=false (retour à l'ancien ordre).
 *
 * Badges à l'arrivée : urgence · sans_rdv · avance · heure · retard.
 * Ordre par docteur : urgences → heure « effective » (RDV, ou arrivée si retard > 15 min) → sans RDV.
 */
class OrdrePassage
{
    public static function actif(): bool
    {
        return (bool) config('cabinet.ordre.actif', true);
    }

    /** Ajoute badge, écart, clé d'ordre et (pour les non arrivés) le retard. Renvoie les RDV triés. */
    public static function preparer(Collection $rdvs, ?Carbon $now = null): Collection
    {
        $now ??= now();
        $tol = (int) config('cabinet.ordre.tolerance', 10);
        $garde = (int) config('cabinet.ordre.retard_garde', 15);
        $absent = (int) config('cabinet.ordre.absent', 45);

        foreach ($rdvs as $r) {
            if ($r->statut === StatutRdv::Arrive && $r->arrive_le) {
                $ecart = (int) round(($r->arrive_le->getTimestamp() - $r->debut->getTimestamp()) / 60); // + = retard
                $badge = $r->urgence ? 'urgence' : ($r->sans_rdv ? 'sans_rdv' : ($ecart < -$tol ? 'avance' : ($ecart > $tol ? 'retard' : 'heure')));
                // heure « effective » : un retard de plus de X min perd sa place (passe après les patients prévus avant son arrivée)
                $effectif = ($r->sans_rdv || $ecart > $garde) ? $r->arrive_le : $r->debut;
                $r->setAttribute('badge', $badge);
                $r->setAttribute('ecart', $ecart);
                $r->setAttribute('cle_ordre', sprintf('%d-%010d-%d-%010d', $r->urgence ? 0 : 1, $effectif->getTimestamp(), $r->sans_rdv ? 1 : 0, $r->arrive_le->getTimestamp()));
            } elseif ($r->statut === StatutRdv::Planifie) {
                $retard = (int) floor(($now->getTimestamp() - $r->debut->getTimestamp()) / 60);
                if ($retard >= $absent) {
                    $r->setAttribute('badge', 'absent_probable');
                } elseif ($retard >= $garde) {
                    $r->setAttribute('badge', 'pas_arrive');
                }
                $r->setAttribute('ecart', $retard);
            }
        }

        return $rdvs->sortBy(fn ($r) => $r->getAttribute('cle_ordre') ?? '9-'.$r->debut->getTimestamp())->values();
    }

    /**
     * Pour un docteur : file triée, attente estimée de chacun, retard du docteur
     * et suggestion quand la salle est libre et que le prochain n'est pas encore dû.
     */
    public static function file(Collection $file, ?\App\Models\RendezVous $enCours, ?string $seanceDepuis, Collection $sansDocteur, bool $pret, ?Carbon $now = null): array
    {
        $now ??= now();
        $duree = fn ($r) => max(10, (int) round(($r->fin->getTimestamp() - $r->debut->getTimestamp()) / 60));
        $retardDoc = 0;
        $libreA = $now->copy();
        if ($enCours) {
            $debutSeance = $seanceDepuis ? Carbon::parse($seanceDepuis) : ($enCours->appele_le ?? $now);
            $finPrevue = $debutSeance->copy()->addMinutes($duree($enCours));
            $retardDoc = max(0, (int) floor(($now->getTimestamp() - $finPrevue->getTimestamp()) / 60));
            $libreA = $finPrevue->greaterThan($now) ? $finPrevue : $now->copy();
        }
        $t = $libreA->copy();
        foreach ($file as $r) {
            // un patient en avance attend son heure (sauf si le docteur le prend plus tôt)
            if ($r->badge === 'avance' && $r->debut->greaterThan($t)) {
                $t = $r->debut->copy();
            }
            $r->setAttribute('attente_estimee', max(0, (int) round(($t->getTimestamp() - $now->getTimestamp()) / 60)));
            $t->addMinutes($duree($r));
        }

        $suggestions = collect();
        $premier = $file->first();
        $rienDu = ! $premier || ($premier->badge === 'avance' && $premier->debut->greaterThan($now->copy()->addMinutes(5)));
        if ($pret && ! $enCours && $rienDu) {
            $suggestions = $file->where('badge', 'avance')->take(2)->merge($sansDocteur->take(2))
                ->map(fn ($r) => ['id' => $r->id, 'numero_appel' => $r->numero_appel, 'patient' => $r->patient?->nom_complet, 'badge' => $r->badge, 'debut' => $r->debut->toIso8601String(), 'sans_rdv' => (bool) $r->sans_rdv])->values();
        }

        return ['retard_min' => $retardDoc, 'suggestions' => $suggestions];
    }
}
