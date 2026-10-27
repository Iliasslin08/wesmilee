<?php

namespace App\Http\Controllers;

use App\Enums\StatutActe;
use App\Models\CatalogueActe;
use App\Models\PlanAValider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Après la consultation, le plan arrive chez la responsable finance (Fadwa) : elle explique le prix,
 * le patient accepte tout, une partie ou rien. Les actes acceptés vont dans « À faire » et elle fixe
 * le docteur et la date ; les actes refusés sont notés « Refusé par le patient ».
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $avec = ['dossier:id,numero,patient_id', 'dossier.patient:id,nom,prenom,telephone', 'proposePar:id,name', 'docteur:id,name', 'validePar:id,name'];

        return Inertia::render('finance/plans', [
            'enAttente' => PlanAValider::with($avec)->where('statut', 'en_attente')->oldest()->get()
                ->map(fn ($p) => $p->toArray() + ['lignes' => $this->lignesAvecPrix($p)]),
            'traites' => PlanAValider::with($avec)->where('statut', '!=', 'en_attente')->latest('valide_le')->limit(30)->get(),
            'docteurs' => User::docteurs()->get(['id', 'name', 'specialite', 'taches_principales', 'fait_consultation', 'duree_rdv']),
        ]);
    }

    public function valider(Request $request, PlanAValider $plan): RedirectResponse
    {
        abort_unless($plan->statut === 'en_attente', 422, 'Ce plan a déjà été traité.');
        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.texte' => ['required', 'string', 'max:255'],
            'lignes.*.prix' => ['nullable', 'numeric', 'min:0'],
            'lignes.*.accepte' => ['boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'prochain' => ['nullable', 'array'],
        ] + (is_array($request->input('prochain')) ? AgendaController::reglesProchain('prochain.') : []), [], ['lignes.*.texte' => 'acte']);

        $message = DB::transaction(function () use ($data, $plan, $request) {
            $dossier = $plan->dossier;
            $moi = $request->user();
            $acceptes = $refuses = [];
            foreach ($data['lignes'] as $l) {
                $acte = $dossier->acteDemande($l['texte'], null, $plan->propose_par ?? $moi->id);
                if (! empty($l['accepte'])) {
                    if (isset($l['prix']) && $l['prix'] !== '') {
                        $acte->update(['prix_convenu' => $l['prix']]);
                    }
                    $acceptes[] = $l['texte'];
                } else {
                    // Refusé : simplement noté dans le dossier
                    $acte->update(['statut' => StatutActe::Annule, 'notes' => trim('Refusé par le patient. '.($data['note'] ?? ''))]);
                    $refuses[] = $l['texte'];
                }
            }

            $msg = $acceptes ? count($acceptes).' acte(s) accepté(s) : dans « À faire ».' : 'Le patient a tout refusé : c’est noté dans le dossier.';
            if ($refuses && $acceptes) {
                $msg .= ' '.count($refuses).' refusé(s), noté(s).';
            }
            if ($acceptes) {
                $dossier->update(['statut' => 'en_traitement']);
                if (! empty($data['prochain'])) {
                    $msg .= ' '.AgendaController::enregistrerProchain($data['prochain'], $dossier, $moi, 'prochain.heure');
                }
            } elseif (! $dossier->actes()->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])->exists()) {
                $dossier->update(['statut' => 'en_suivi']);
            }

            $plan->update([
                'actes' => implode("\n", array_column($data['lignes'], 'texte')),
                'statut' => $acceptes ? 'valide' : 'refuse',
                'reponse' => trim(($acceptes ? 'Accepté : '.implode(', ', $acceptes).'. ' : '').($refuses ? 'Refusé : '.implode(', ', $refuses).'. ' : '').($data['note'] ?? '')),
                'valide_par' => $moi->id, 'valide_le' => now(),
                'docteur_id' => $data['prochain']['docteur_id'] ?? $plan->docteur_id,
            ]);

            return $msg;
        });
        $this->ok($message);

        return back();
    }

    /** Chaque ligne du plan avec son prix : celui de l'acte déjà prévu, sinon celui du catalogue. */
    private function lignesAvecPrix(PlanAValider $plan): array
    {
        $ouverts = $plan->dossier?->actes()->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])->get() ?? collect();

        return array_map(function ($texte) use ($ouverts) {
            $norm = fn ($t) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($t)));
            $acte = $ouverts->first(fn ($a) => $norm($a->libelle.($a->dents ? ' · '.$a->dents : '')) === $norm($texte));
            $libelle = preg_replace('/\s+·\s+[^·]+$/u', '', $texte);
            $prix = $acte?->prix_convenu ?: (CatalogueActe::where('libelle', $libelle)->value('prix_defaut')
                ?? CatalogueActe::where('libelle', 'like', $libelle.'%')->orderBy('id')->value('prix_defaut'));

            return ['texte' => $texte, 'prix' => $prix !== null ? (float) $prix : null];
        }, $plan->lignes());
    }
}
