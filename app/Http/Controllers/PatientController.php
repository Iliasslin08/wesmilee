<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Dossier;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    /** Tous les dossiers : le dossier est partagé entre tous les acteurs du cabinet. */
    public function index(Request $request): Response
    {
        // Labo : seule la cheffe consulte les dossiers ; les autres voient uniquement leurs travaux
        abort_if($request->user()->role === \App\Enums\Role::Labo && ! $request->user()->estChefLabo(), 403);
        $filtres = $request->only(['q', 'docteur', 'statut', 'tri']);
        // Corbeille : dossiers supprimés (chef), qu'on peut restaurer
        $corbeille = $request->boolean('corbeille') && $request->user()->isAdmin();
        $patients = Patient::query()
            ->when($corbeille, fn ($q) => $q->onlyTrashed()->whereHas('dossier', fn ($d) => $d->withTrashed()), fn ($q) => $q->whereHas('dossier'))
            ->where('statut_fiche', 'validee')
            ->with([
                'dossier' => fn ($d) => $d->withTrashed()->select('id', 'patient_id', 'numero', 'statut', 'docteur_consultation_id', 'ancien_numero_papier', 'deleted_at'),
                'dossier.docteurConsultation:id,name',
                'rendezVous' => fn ($r) => $r->where('debut', '>=', now()->startOfDay())->whereIn('statut', ['planifie', 'arrive'])
                    ->orderBy('debut')->with('docteur:id,name')->limit(1),
            ])
            ->withMax(['rendezVous as derniere_visite' => fn ($r) => $r->where('statut', 'termine')], 'debut')
            ->recherche($filtres['q'] ?? '')
            ->when($filtres['statut'] ?? null, fn ($q, $st) => $q->whereHas('dossier', fn ($d) => $d->where('statut', $st)))
            ->when($filtres['docteur'] ?? null, fn ($q, $id) => $q->where(fn ($x) => $x
                ->whereHas('dossier', fn ($d) => $d->where('docteur_consultation_id', $id)->orWhereHas('seances', fn ($se) => $se->where('docteur_id', $id)))
                ->orWhereHas('rendezVous', fn ($r) => $r->where('docteur_id', $id))))
            ->when(($filtres['tri'] ?? '') === 'recent', fn ($q) => $q->orderByDesc('derniere_visite'), fn ($q) => $q->orderBy('nom')->orderBy('prenom'))
            ->paginate(25)->withQueryString();

        return Inertia::render('patients/index', [
            'patients' => $patients,
            'filtres' => $filtres + ['q' => ''],
            'docteurs' => \App\Models\User::docteurs()->get(['id', 'name']),
            'peutCreer' => $request->user()->hasRole(Role::Reception),
            'peutSupprimer' => $request->user()->isAdmin(),
            'corbeille' => $corbeille,
        ]);
    }

    /** Fiches remplies sur la tablette, à valider par la réception. */
    public function fiches(): Response
    {
        return Inertia::render('reception/fiches', [
            'fiches' => Patient::where('statut_fiche', 'a_valider')->latest()->get()
                ->map(fn (Patient $f) => $f->toArray() + ['doublons' => Patient::doublonsPossibles($f->toArray() + ['date_naissance' => $f->date_naissance?->toDateString()], $f->id)]),
            'docteurs' => \App\Models\User::docteurs()->get(['id', 'name', 'specialite']),
            'antecedents' => AccueilController::ANTECEDENTS,
        ]);
    }

    /** La réception valide la fiche : le dossier numérique est créé. */
    public function valider(Request $request, Patient $patient): RedirectResponse
    {
        $data = $this->valider_donnees($request, $patient) + $request->validate([
            'ancien_numero_papier' => ['nullable', 'string', 'max:20'],
            'cin_fichiers' => ['nullable', 'array', 'max:4'],
            'cin_fichiers.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,pdf'],
            'papiers' => ['nullable', 'array', 'max:10'],
            'papiers.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,pdf'],
        ]);

        $patient->update(collect($data)->except(['ancien_numero_papier', 'cin_fichiers', 'papiers'])->all() + [
            'statut_fiche' => 'validee', 'valide_par' => $request->user()->id, 'valide_le' => now(),
        ]);

        $dossier = $patient->dossier ?? Dossier::create([
            'patient_id' => $patient->id,
            'ancien_numero_papier' => $data['ancien_numero_papier'] ?? null,
            'cree_par' => $request->user()->id,
        ]);

        // CIN et papiers scannés au moment de la validation
        foreach (['cin' => 'cin_fichiers', 'autre' => 'papiers'] as $type => $champ) {
            foreach ($request->file($champ, []) as $f) {
                $patient->documents()->create([
                    'type' => $type,
                    'chemin' => $f->store("patients/{$patient->id}", 'local'),
                    'nom_original' => $f->getClientOriginalName(),
                    'ajoute_par' => $request->user()->id,
                ]);
            }
        }

        $this->ok("Dossier n° {$dossier->numero} créé pour {$patient->nom_complet}.");

        return redirect()->route('dossiers.show', $dossier);
    }

    /** La fiche tablette appartient à un patient qui a déjà un dossier : on met à jour son dossier. */
    public function rattacher(Request $request, Patient $patient, Patient $existant): RedirectResponse
    {
        abort_if($patient->statut_fiche !== 'a_valider' || $existant->statut_fiche !== 'validee', 422);
        \App\Services\FusionPatients::completerPatient($existant, $patient);
        // les réponses récentes de la fiche (allergies, traitements) priment
        $existant->update(array_filter([
            'traitements' => $patient->traitements,
            'profession' => $patient->profession,
            'adresse' => $patient->adresse,
            'ville' => $patient->ville,
        ]));
        $existant->update(['fiche_a_completer' => false]);
        $patient->documents()->update(['patient_id' => $existant->id]);
        $patient->forceDelete();
        $dossier = $existant->dossier;
        $this->ok("Fiche rattachée au dossier n° {$dossier?->numero} de {$existant->nom_complet}.");

        return $dossier ? redirect()->route('dossiers.show', $dossier) : back();
    }

    /** Création directe par la réception (patient qui ne passe pas par la tablette). */
    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider_donnees($request);
        $doublons = Patient::doublonsPossibles($donnees);
        if ($doublons->isNotEmpty() && ! $request->boolean('creer_quand_meme')) {
            $d = $doublons->first();
            throw \Illuminate\Validation\ValidationException::withMessages([
                'nom' => "Ce patient existe peut-être déjà : {$d['nom_complet']} (".implode(', ', $d['raisons']).
                    (isset($d['dossier']['numero']) ? ", dossier n° {$d['dossier']['numero']}" : '').'). Cochez « créer quand même » si c\'est une autre personne.',
            ]);
        }
        $patient = Patient::create($donnees + [
            'statut_fiche' => 'validee', 'valide_par' => $request->user()->id, 'valide_le' => now(),
            // fiche incomplète (sans date de naissance) : le patient la confirmera sur la tablette
            'fiche_a_completer' => empty($donnees['date_naissance']),
        ]);
        $dossier = Dossier::create([
            'patient_id' => $patient->id,
            'ancien_numero_papier' => $request->input('ancien_numero_papier'),
            'cree_par' => $request->user()->id,
        ]);
        $this->ok("Dossier n° {$dossier->numero} créé.");

        return redirect()->route('dossiers.show', $dossier);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $data = $this->valider_donnees($request, $patient);
        if (($data['telephone'] ?? null) && $patient->telephone && $data['telephone'] !== $patient->telephone) {
            $data['anciens_telephones'] = array_values(array_unique([...($patient->anciens_telephones ?? []), $patient->telephone]));
        }
        // La réception a complété la fiche elle-même (date de naissance saisie) : dossier confirmé
        if (! empty($data['date_naissance'])) {
            $data['fiche_a_completer'] = false;
        }
        $patient->update($data);
        $this->ok('Fiche patient mise à jour.');

        return back();
    }

    /** Restaurer un dossier supprimé (corbeille du chef). */
    public function restaurer(int $patient): RedirectResponse
    {
        $p = Patient::onlyTrashed()->findOrFail($patient);
        $p->restore();
        \App\Models\Dossier::onlyTrashed()->where('patient_id', $p->id)->restore();
        $this->ok("Dossier de {$p->nom_complet} restauré.");

        return back();
    }

    /** Envoie le patient à la tablette de l'accueil : la fiche s'ouvre à son nom, il la complète. */
    public function versTablette(Patient $patient): RedirectResponse
    {
        Cache::put(AccueilController::CLE_TABLETTE, $patient->id, now()->addMinutes(30));
        $this->ok("La tablette affiche maintenant la fiche de {$patient->nom_complet}. Donnez-lui la tablette.");

        return back();
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        abort_if($patient->statut_fiche !== 'a_valider', 403);
        $patient->forceDelete();
        $this->ok('Fiche supprimée.');

        return back();
    }

    private function valider_donnees(Request $request, ?Patient $patient = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'sexe' => ['nullable', 'in:F,M'],
            'cin' => ['nullable', 'string', 'max:20', Rule::unique('patients', 'cin')->ignore($patient?->id)->whereNull('deleted_at')],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'ville' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'antecedents' => ['nullable', 'array'],
            'antecedents_autres' => ['nullable', 'string', 'max:1000'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'traitements' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
