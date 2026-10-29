<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutActe;
use App\Models\CatalogueActe;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\EtatDent;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DossierController extends Controller
{
    public function show(Request $request, Dossier $dossier): Response|RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === Role::Labo && ! $user->estChefLabo(), 403);
        // Dossier fusionné (doublon) : on ouvre celui qui a été gardé
        if ($dossier->trashed()) {
            abort_unless($dossier->fusionne_dans_id, 404);

            return redirect()->route('dossiers.show', $dossier->fusionne_dans_id);
        }
        $dossier->load(['patient', 'docteurConsultation:id,name']);

        $actes = $dossier->actes()->with(['prevuPar:id,name', 'realisePar:id,name'])
            ->orderByRaw("case statut when 'en_cours' then 0 when 'a_faire' then 1 when 'fait' then 2 else 3 end")
            ->latest('updated_at')->get();

        // Seuls la responsable finance (paiements de ce patient) et le chef voient l'argent
        $voitFinance = $user->hasRole(Role::Finance);
        if (! $voitFinance) {
            $actes->each->makeHidden('prix_convenu');
        }

        $seanceOuverte = Seance::where('dossier_id', $dossier->id)->where('docteur_id', $user->id)
            ->whereNull('fin')->with('docteur:id,name')->latest('debut')->first();

        return Inertia::render('dossiers/show', [
            'dossier' => $dossier,
            'alertes' => $voitFinance ? $dossier->alertes() : array_values(array_filter($dossier->alertes(), fn ($a) => ! str_starts_with($a['texte'], 'Reste'))),
            'actes' => $actes,
            'seances' => $dossier->seances()->with(['docteur:id,name', 'actes:id,seance_id,libelle,dents'])->get(),
            'seanceOuverte' => $seanceOuverte,
            'dents' => $dossier->etatsDents()->pluck('etat', 'dent'),
            'etatsDents' => EtatDent::ETATS,
            'documents' => $dossier->patient->documents()->with('ajoutePar:id,name')->get(),
            // Carte patient (photo + QR code) : photographe, réception, docteurs
            'carte' => $user->hasRole(Role::Reception, Role::Docteur, Role::Photographe)
                ? ['photo' => $dossier->patient->photoCarte()?->id, 'lien' => $dossier->patient->lienCarte()]
                : null,
            'typesDocuments' => Document::TYPES,
            'equipeLabo' => User::equipeLabo()->get(['id', 'name', 'taches_principales', 'taches_secondaires']),
            'commandes' => $dossier->commandesLabo()->with('acte:id,libelle', 'technicien:id,name')->get()
                ->map(fn ($c) => $c->toArray() + ['priorite' => $c->priorite()]),
            'plansDossier' => \App\Models\PlanAValider::where('dossier_id', $dossier->id)->where('statut', 'en_attente')
                ->with('proposePar:id,name', 'docteur:id,name')->get(),
            'avis' => $user->isAdmin() ? $dossier->patient->avis()->get(['id', 'commentaire', 'created_at']) : [],
            'paiements' => $voitFinance ? $dossier->paiements()->with('encaissePar:id,name')->get() : [],
            'finance' => $voitFinance ? [
                'convenu' => $dossier->totalConvenu(),
                'recu' => $dossier->totalRecu(),
                'reste' => $dossier->reste(),
            ] : null,
            'prochainRdv' => $dossier->patient->rendezVous()->with('docteur:id,name')
                ->where('debut', '>=', now())->whereIn('statut', ['planifie', 'arrive'])->orderBy('debut')->first(),
            'rdvAFixerDossier' => \App\Models\RdvAFixer::where('patient_id', $dossier->patient_id)->where('statut', 'en_attente')
                ->with('docteur:id,name', 'demandePar:id,name')->get(),
            'rendezVous' => $dossier->patient->rendezVous()->with('docteur:id,name')->latest('debut')->limit(10)->get(),
            'catalogue' => CatalogueActe::where('actif', true)->orderBy('libelle')->get()->each(fn ($c) => $voitFinance ? null : $c->makeHidden('prix_defaut')),
            'docteurs' => User::docteurs()->get(['id', 'name', 'specialite', 'taches_principales', 'fait_consultation', 'duree_rdv']),
            'antecedentsListe' => AccueilController::ANTECEDENTS,
            'radios' => $dossier->radios()->with(['documents:id,radio_id,nom_original', 'fairePar:id,name'])->get()
                ->map(fn ($r) => $r->toArray() + ['type_label' => \App\Models\Radio::TYPES[$r->type] ?? $r->type]),
            'typesRadio' => \App\Models\Radio::TYPES,
            'ordonnances' => $dossier->ordonnances()->with('docteur:id,name')->get(),
            'suggestionsOrdonnance' => \App\Models\Ordonnance::SUGGESTIONS,
            'demandesRadioDossier' => $dossier->demandesRadio()->where('statut', 'en_attente')->with('demandePar:id,name')->get(),
            'peut' => [
                'identite' => $user->hasRole(Role::Reception),
                'medical' => $user->hasRole(Role::Docteur),
                // Écrire des actes : docteur ; réception = reprise des anciens dossiers papier
                'actes' => $user->hasRole(Role::Docteur, Role::Reception),
                'papier' => $user->role === Role::Reception,
                'antecedents' => $user->hasRole(Role::Docteur, Role::Reception),
                'finance' => $user->hasRole(Role::Finance),
                'prix' => $user->hasRole(Role::Finance),
                'documents' => $user->hasRole(Role::Reception, Role::Docteur, Role::Photographe, Role::Radio),
                'radio' => $user->role === Role::Radio,
                'demanderRadio' => $user->hasRole(Role::Docteur, Role::Reception),
                'labo' => $user->hasRole(Role::Docteur, Role::Reception, Role::Finance),
                'rdvTous' => $user->hasRole(Role::Reception),
                'supprimer' => $user->isAdmin(),
                'rdvMiens' => $user->role === Role::Docteur ? $user->id : null,
                // Docteur de consultation : pas d'envoi direct ni de prochain RDV, il propose le plan au chef
                'envoyer' => ! $user->passeParLeChef(),
            ],
        ]);
    }

    /** Supprimer un dossier (chef) : il va dans la corbeille, rien n'est perdu. Les RDV à venir sont annulés. */
    public function supprimer(Dossier $dossier): RedirectResponse
    {
        $patient = $dossier->patient;
        $patient->rendezVous()->whereIn('statut', ['planifie', 'arrive'])->update(['statut' => 'annule']);
        \App\Models\RdvAFixer::where('patient_id', $patient->id)->where('statut', 'en_attente')->update(['statut' => 'annule']);
        $dossier->delete();
        $patient->delete();
        $this->ok("Dossier n° {$dossier->numero} de {$patient->nom_complet} supprimé (restaurable depuis la corbeille).");

        return redirect()->route('patients.index');
    }

    public function updateMedical(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'diagnostic' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'statut' => ['required', 'in:en_traitement,en_suivi'],
            'prochain_controle' => ['nullable', 'date'],
            'docteur_consultation_id' => ['nullable', 'exists:users,id'],
        ]);
        $dossier->update($data);
        $this->ok('Dossier médical enregistré.');

        return back();
    }

    /** Antécédents, allergies, traitements : modifiables par la réception et les docteurs. */
    public function updateAntecedents(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'antecedents' => ['nullable', 'array'],
            'antecedents.*' => ['string', 'in:'.implode(',', array_keys(AccueilController::ANTECEDENTS))],
            'antecedents_autres' => ['nullable', 'string', 'max:1000'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'traitements' => ['nullable', 'string', 'max:500'],
        ]);
        $dossier->patient->update($data + ['antecedents' => $data['antecedents'] ?? []]);
        $this->ok('Antécédents enregistrés.');

        return back();
    }

    /** Fusion de deux dossiers du même patient : on garde celui-ci, l'autre y est versé. */
    public function fusionner(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate(['autre_dossier_id' => ['required', 'exists:dossiers,id', \Illuminate\Validation\Rule::notIn([$dossier->id])]]);
        $autre = Dossier::findOrFail($data['autre_dossier_id']);
        \App\Services\FusionPatients::fusionner($dossier, $autre);
        $this->ok("Dossier n° {$autre->numero} fusionné dans le dossier n° {$dossier->numero}.");

        return redirect()->route('dossiers.show', $dossier);
    }

    public function updateDent(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'dent' => ['required', 'integer', 'between:11,85'],
            'etat' => ['required', 'in:'.implode(',', EtatDent::ETATS)],
        ]);
        if ($data['etat'] === 'saine') {
            $dossier->etatsDents()->where('dent', $data['dent'])->delete();
        } else {
            $dossier->etatsDents()->updateOrCreate(['dent' => $data['dent']], ['etat' => $data['etat']]);
        }

        return back();
    }

    /** Patients à rappeler : actes « À faire » sans rendez-vous ou contrôle dépassé. */
    public function aRappeler(): Response
    {
        $dossiers = Dossier::with('patient')
            ->where(fn ($q) => $q
                ->whereHas('actes', fn ($a) => $a->where('statut', StatutActe::AFaire->value)
                    ->whereDoesntHave('rendezVous', fn ($r) => $r->where('debut', '>=', now()->startOfDay())))
                ->orWhere('prochain_controle', '<=', now()->addWeek()))
            ->limit(200)->get()
            ->map(fn (Dossier $d) => [
                'id' => $d->id, 'numero' => $d->numero, 'patient' => $d->patient,
                'prochain_controle' => $d->prochain_controle?->toDateString(),
                'actes_a_faire' => $d->actes()->where('statut', StatutActe::AFaire->value)->pluck('libelle'),
            ]);

        return Inertia::render('reception/a-rappeler', ['dossiers' => $dossiers]);
    }
}
