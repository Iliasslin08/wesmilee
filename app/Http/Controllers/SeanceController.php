<?php

namespace App\Http\Controllers;

use App\Enums\EtatDocteur;
use App\Enums\StatutActe;
use App\Enums\StatutRdv;
use App\Models\Dossier;
use App\Models\RendezVous;
use App\Models\Seance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeanceController extends Controller
{
    /** Démarre une séance (si le patient n'est pas passé par « Envoyer » à la réception). */
    public function demarrer(Request $request, Dossier $dossier): RedirectResponse
    {
        $user = $request->user();
        $rdv = RendezVous::where('patient_id', $dossier->patient_id)->where('docteur_id', $user->id)
            ->duJour()->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value, StatutRdv::EnCours->value])
            ->orderBy('debut')->first();

        self::ouvrir($dossier, $user->id, $rdv);
        $this->ok('Séance démarrée à '.now()->format('H:i').'.');

        return back();
    }

    public static function ouvrir(Dossier $dossier, int $docteurId, ?RendezVous $rdv): Seance
    {
        $seance = Seance::firstOrCreate(
            ['dossier_id' => $dossier->id, 'docteur_id' => $docteurId, 'fin' => null],
            ['debut' => now(), 'rendez_vous_id' => $rdv?->id],
        );
        $rdv?->update(['statut' => StatutRdv::EnCours]);
        $seance->docteur->changerEtat(EtatDocteur::Occupe);

        return $seance;
    }

    public function terminer(Request $request, Seance $seance): RedirectResponse
    {
        abort_unless($seance->docteur_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'compte_rendu' => ['nullable', 'string', 'max:5000'],
            'suite' => ['nullable', 'string', 'max:2000'],
            'actes_faits' => ['array'],
            'actes_faits.*' => ['integer'],
            'actes_en_cours' => ['array'],
            'actes_en_cours.*' => ['integer'],
            'envoyer_docteur_id' => ['nullable', 'exists:users,id'],
            'envoyer_mission' => ['nullable', 'required_with:envoyer_docteur_id', 'string', 'max:255'],
            'prochain' => ['nullable', 'array'],
            'faits_texte' => ['nullable', 'string', 'max:3000'],
            'a_faire_texte' => ['nullable', 'string', 'max:3000'],
            'plan' => ['nullable', 'array'],
            'plan.actes' => ['required_with:plan', 'string', 'max:3000'],
            'plan.docteur_id' => ['nullable', 'exists:users,id'],
            'plan.notes' => ['nullable', 'string', 'max:2000'],
        ] + (is_array($request->input('prochain')) ? AgendaController::reglesProchain('prochain.') : []));

        // Docteur de consultation : il n'envoie pas lui-même le patient, la finance présente d'abord le devis
        if ($request->user()->passeParLeChef() && (! empty($data['envoyer_docteur_id']) || ! empty($data['prochain']))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plan' => 'Après une consultation, le plan est envoyé à la finance (devis au patient).']);
        }

        return DB::transaction(fn () => $this->cloturer($request, $seance, $data));
    }

    private function cloturer(Request $request, Seance $seance, array $data): RedirectResponse
    {

        $fin = now();
        $seance->update([
            'fin' => $fin,
            'duree_minutes' => (int) round($seance->debut->diffInMinutes($fin)),
            'compte_rendu' => $data['compte_rendu'] ?? null,
            'suite' => $data['suite'] ?? null,
        ]);

        $actes = $seance->dossier->actes();
        (clone $actes)->whereIn('id', $data['actes_faits'] ?? [])->get()->each->update([
            'statut' => StatutActe::Fait, 'realise_par' => $seance->docteur_id, 'seance_id' => $seance->id, 'fait_le' => $fin,
        ]);
        (clone $actes)->whereIn('id', $data['actes_en_cours'] ?? [])->where('statut', StatutActe::AFaire->value)
            ->get()->each->update(['statut' => StatutActe::EnCours]);

        // Actes écrits à la main : « Fait aujourd'hui » et « À faire »
        $lignes = fn (?string $t) => array_filter(array_map('trim', preg_split('/\R/', (string) $t)));
        foreach ($lignes($data['faits_texte'] ?? null) as $l) {
            $seance->dossier->acteFait($l, $seance->docteur_id, $seance->id);
        }
        foreach ($lignes($data['a_faire_texte'] ?? null) as $l) {
            $seance->dossier->acteDemande($l, null, $seance->docteur_id);
        }

        $seance->rendezVous?->update(['statut' => StatutRdv::Termine]);
        $request->user()->changerEtat(EtatDocteur::Pret);

        $dossier = $seance->dossier;
        if (! $dossier->actes()->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])->exists()) {
            $dossier->update(['statut' => 'en_suivi']);
        }

        // Le patient part chez un autre docteur aujourd'hui : il quitte ma liste
        // et arrive dans la salle d'attente / la liste du docteur suivant.
        $message = 'Séance terminée ('.$seance->duree_minutes.' min). Vous êtes « Prêt » pour le patient suivant.';
        if (! empty($data['envoyer_docteur_id'])) {
            $ancien = $seance->rendezVous;
            $suivant = RendezVous::create([
                'patient_id' => $dossier->patient_id,
                'docteur_id' => $data['envoyer_docteur_id'],
                // le travail à faire chez l'autre docteur va dans « À faire »
                'acte_id' => $dossier->acteDemande($data['envoyer_mission'], null, $request->user()->id)->id,
                'mission' => $data['envoyer_mission'],
                'debut' => now(), 'fin' => now()->addMinutes(30),
                'sans_rdv' => true, 'urgence' => (bool) $ancien?->urgence,
                'statut' => StatutRdv::Arrive, 'arrive_le' => now(),
                'numero_appel' => $ancien?->numero_appel ?? SalleAttenteController::prochainNumero(),
                'cree_par' => $request->user()->id,
            ]);
            $message = "Séance terminée. {$dossier->patient->nom_complet} est envoyé(e) à {$suivant->docteur->name} ({$suivant->mission}).";
        }

        // Prochain rendez-vous (souvent chez un autre docteur) : date fixée maintenant, ou par la réception
        if (! empty($data['prochain'])) {
            $message .= ' '.AgendaController::enregistrerProchain($data['prochain'], $dossier, $request->user(), 'prochain.heure');
        }

        // Plan envoyé à la finance (docteur de consultation)
        if (! empty($data['plan']['actes']) && trim($data['plan']['actes']) !== '') {
            \App\Models\PlanAValider::create([
                'dossier_id' => $dossier->id, 'seance_id' => $seance->id, 'propose_par' => $request->user()->id,
                'docteur_id' => $data['plan']['docteur_id'] ?? null, 'actes' => trim($data['plan']['actes']), 'notes' => $data['plan']['notes'] ?? null,
            ]);
            $message .= ' Le plan est envoyé à la finance : elle présente le devis au patient.';
        }

        $this->ok($message);

        return back();
    }
}
