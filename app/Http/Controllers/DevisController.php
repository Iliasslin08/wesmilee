<?php

namespace App\Http\Controllers;

use App\Enums\StatutActe;
use App\Models\Dossier;
use App\Models\PlanAValider;
use Illuminate\View\View;

/**
 * Devis imprimable (même présentation que le devis papier) : actes, dents, montant à payer.
 * Réservé à la finance : les docteurs ne voient pas les prix.
 */
class DevisController extends Controller
{
    /** Devis des actes encore à faire / en cours du dossier. */
    public function dossier(Dossier $dossier): View
    {
        $lignes = $dossier->actes()
            ->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])
            ->orderBy('id')->get()
            ->map(fn ($a) => ['acte' => $a->libelle, 'dents' => (string) $a->dents, 'prix' => (float) $a->prix_convenu])
            ->all();

        return $this->vue($dossier, $lignes);
    }

    /** Devis d'un plan proposé après la consultation (avant que le patient accepte). */
    public function plan(PlanAValider $plan, PlanController $plans): View
    {
        abort_unless($plan->dossier, 404);
        $lignes = array_map(function ($l) {
            // « Couronne · 36, 37 » → acte « Couronne », dents « 36, 37 »
            $morceaux = preg_split('/\s+·\s+/u', $l['texte']);
            $dents = count($morceaux) > 1 && preg_match('/^[\d\s,;\-\/àa]+$/u', end($morceaux)) ? array_pop($morceaux) : '';

            return ['acte' => implode(' · ', $morceaux), 'dents' => $dents, 'prix' => (float) ($l['prix'] ?? 0)];
        }, $plans->lignesAvecPrix($plan));

        return $this->vue($plan->dossier, $lignes);
    }

    private function vue(Dossier $dossier, array $lignes): View
    {
        return view('devis', [
            'dossier' => $dossier->load('patient'),
            'lignes' => $lignes,
            'cabinet' => config('cabinet'),
        ]);
    }
}
