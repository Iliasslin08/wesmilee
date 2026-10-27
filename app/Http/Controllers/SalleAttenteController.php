<?php

namespace App\Http\Controllers;

use App\Enums\EtatDocteur;
use App\Enums\StatutRdv;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalleAttenteController extends Controller
{
    public function index(): Response
    {
        $rdvs = RendezVous::duJour()->whereNotIn('statut', [StatutRdv::Annule->value])
            ->with(['patient:id,nom,prenom,ville,telephone,allergies,fiche_a_completer', 'patient.dossier:id,patient_id,numero', 'docteur:id,name'])
            ->orderByDesc('urgence')->orderBy('debut')->get();
        self::typeVisite($rdvs);
        // Patient venu aujourd'hui (urgence, sans RDV…) qui a AUSSI un RDV plus tard : on le signale pour pouvoir le supprimer
        $futurs = RendezVous::whereIn('patient_id', $rdvs->pluck('patient_id'))->where('debut', '>', now()->endOfDay())
            ->where('statut', StatutRdv::Planifie->value)->with('docteur:id,name')->orderBy('debut')->get()->groupBy('patient_id');
        foreach ($rdvs as $r) {
            $r->setAttribute('autres_rdv', ($futurs[$r->patient_id] ?? collect())->map(fn ($f) => ['id' => $f->id, 'debut' => $f->debut->toIso8601String(), 'mission' => $f->mission, 'docteur' => $f->docteur?->name])->values());
        }

        $docteurs = User::docteurs()->get(['id', 'name', 'specialite', 'etat', 'etat_depuis', 'salle', 'couleur', 'taches_principales', 'fait_consultation', 'duree_rdv'])
            ->map(function (User $d) use ($rdvs) {
                $mesRdvs = $rdvs->where('docteur_id', $d->id);

                return $d->toArray() + [
                    'etat_label' => $d->etat?->label(),
                    'en_seance' => $mesRdvs->firstWhere('statut', StatutRdv::EnCours)?->patient?->nom_complet,
                    // prêts à partir chez le docteur : arrivés ET fiche confirmée
                    'en_attente' => $mesRdvs->where('statut', StatutRdv::Arrive)->reject(fn ($r) => $r->patient->fiche_a_completer)->values()->map->only(['id', 'numero_appel', 'urgence', 'patient']),
                    'seance_depuis' => \App\Models\Seance::where('docteur_id', $d->id)->whereNull('fin')->min('debut'),
                ];
            });

        return Inertia::render('reception/salle-attente', [
            'rdvs' => $rdvs,
            'docteurs' => $docteurs,
            'maintenant' => now()->toIso8601String(),
        ]);
    }

    /**
     * Type de visite de chaque RDV : 1re consultation (sans RDV ou prise au téléphone) ou suivi (séance n° X).
     * Ajoute « visite » et « seance_num » à chaque rendez-vous.
     */
    public static function typeVisite(\Illuminate\Support\Collection $rdvs): void
    {
        $dossiers = $rdvs->pluck('patient.dossier.id')->filter()->unique();
        $faites = \App\Models\Seance::whereIn('dossier_id', $dossiers)->whereNotNull('fin')
            ->selectRaw('dossier_id, count(*) as n')->groupBy('dossier_id')->pluck('n', 'dossier_id');
        foreach ($rdvs as $r) {
            $n = (int) ($faites[$r->patient?->dossier?->id] ?? 0);
            // séance du jour déjà terminée : on ne la compte pas
            if ($r->statut === StatutRdv::Termine && $n > 0) {
                $n--;
            }
            $r->setAttribute('visite', $n === 0 ? ($r->sans_rdv ? 'premiere_sans_rdv' : 'premiere') : 'suivi');
            $r->setAttribute('seance_num', $n + 1);
        }
    }

    public function arrive(RendezVous $rdv): RedirectResponse
    {
        $rdv->update(['statut' => StatutRdv::Arrive, 'arrive_le' => now(), 'numero_appel' => $rdv->numero_appel ?? self::prochainNumero()]);
        if ($dossier = $rdv->patient->dossier) {
            $dossier->acteDeVisite($rdv, auth()->id());
            \App\Models\DemandeRadio::panoramiqueSiBesoin($dossier, auth()->id(), (string) $rdv->mission);
        }
        if ($rdv->patient->fiche_a_completer) {
            $this->erreur("{$rdv->patient->nom_complet} est arrivé(e) · N° {$rdv->numero_appel}. ⚠ Fiche non remplie : envoyez le patient à la tablette.");
        } else {
            $this->ok("{$rdv->patient->nom_complet} est arrivé(e) · N° {$rdv->numero_appel}");
        }

        return back();
    }

    public function absent(RendezVous $rdv): RedirectResponse
    {
        $rdv->update(['statut' => StatutRdv::Absent]);

        return back();
    }

    /** Patient venu sans rendez-vous (ou en urgence). */
    public function sansRdv(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'required_without:nouveau', 'exists:patients,id'],
            'nouveau' => ['nullable', 'array'],
            'nouveau.nom' => ['required_with:nouveau', 'string', 'max:100'],
            'nouveau.prenom' => ['required_with:nouveau', 'string', 'max:100'],
            'nouveau.telephone' => ['required_with:nouveau', 'string', 'max:20'],
            'creer_quand_meme' => ['boolean'],
            'docteur_id' => ['nullable', 'exists:users,id'],
            'mission' => ['required', 'string', 'max:255'],
            'urgence' => ['boolean'],
        ]);

        // Nouveau patient saisi rapidement par la réception : dossier créé tout de suite,
        // il complétera sa fiche (CIN, antécédents) ensuite.
        if (empty($data['patient_id']) && ! empty($data['nouveau'])) {
            $doublons = Patient::doublonsPossibles($data['nouveau']);
            if ($doublons->isNotEmpty() && empty($data['creer_quand_meme'])) {
                $d = $doublons->first();
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'nouveau' => "Ce patient existe peut-être déjà : {$d['nom_complet']} (".implode(', ', $d['raisons']).
                        (isset($d['dossier']['numero']) ? ", dossier n° {$d['dossier']['numero']}" : '').'). Cherchez-le dans « Patient », ou cochez « créer quand même ».',
                ]);
            }
            $patient = Patient::create($data['nouveau'] + [
                'fiche_a_completer' => true, 'statut_fiche' => 'validee', 'valide_par' => $request->user()->id, 'valide_le' => now(),
            ]);
            \App\Models\Dossier::create(['patient_id' => $patient->id, 'cree_par' => $request->user()->id]);
            $data['patient_id'] = $patient->id;
        }
        unset($data['nouveau'], $data['creer_quand_meme']);

        // Déjà dans la salle d'attente aujourd'hui ? On ne le rajoute pas une 2e fois.
        $actif = RendezVous::duJour()->where('patient_id', $data['patient_id'])
            ->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value, StatutRdv::EnCours->value])->orderBy('debut')->first();
        if ($actif) {
            if ($actif->statut === StatutRdv::Planifie) {
                $actif->update(['statut' => StatutRdv::Arrive, 'arrive_le' => now(), 'numero_appel' => $actif->numero_appel ?? self::prochainNumero(), 'urgence' => $actif->urgence || ! empty($data['urgence'])]);
                $this->ok("{$actif->patient->nom_complet} avait déjà un rendez-vous aujourd'hui : marqué arrivé · N° {$actif->numero_appel}");
            } else {
                $this->erreur("{$actif->patient->nom_complet} est déjà dans la salle d'attente (N° {$actif->numero_appel}).");
            }

            return back();
        }

        $rdv = RendezVous::create($data + [
            'debut' => now(), 'fin' => now()->addMinutes(30), 'sans_rdv' => true,
            'statut' => StatutRdv::Arrive, 'arrive_le' => now(), 'numero_appel' => self::prochainNumero(),
            'cree_par' => $request->user()->id,
        ]);
        if ($dossier = $rdv->patient->dossier) {
            $dossier->acteDeVisite($rdv, $request->user()->id);
            \App\Models\DemandeRadio::panoramiqueSiBesoin($dossier, $request->user()->id, $rdv->mission);
        }
        $this->ok("Ajouté à la salle d'attente · N° {$rdv->numero_appel}");

        return back();
    }

    /** La réception envoie le patient au docteur : appel sur l'écran TV + début de séance automatique. */
    public function envoyer(Request $request, RendezVous $rdv): RedirectResponse
    {
        $data = $request->validate(['docteur_id' => ['nullable', 'exists:users,id']]);
        if (! empty($data['docteur_id'])) {
            $rdv->docteur_id = $data['docteur_id'];
        }
        abort_unless($rdv->docteur_id, 422, 'Choisissez un docteur.');
        // Ordre : arrivée → tablette (fiche) → salle d'attente → docteur
        if ($rdv->patient->fiche_a_completer && ! $request->boolean('forcer')) {
            $this->erreur("{$rdv->patient->nom_complet} n'a pas encore rempli sa fiche : envoyez-le d'abord à la tablette.");

            return back();
        }

        $rdv->update(['appele_le' => now(), 'statut' => StatutRdv::EnCours, 'numero_appel' => $rdv->numero_appel ?? self::prochainNumero()]);
        if ($dossier = $rdv->patient->dossier) {
            SeanceController::ouvrir($dossier, $rdv->docteur_id, $rdv);
        } else {
            $rdv->docteur->changerEtat(EtatDocteur::Occupe);
        }
        $this->ok("N° {$rdv->numero_appel} appelé vers {$rdv->docteur->name}.");

        return back();
    }

    public static function prochainNumero(): int
    {
        return (int) RendezVous::duJour()->max('numero_appel') + 1;
    }

    /** Écran TV : dernier appel, toutes les salles (patient → docteur) et la file d'attente. Actualisation automatique. */
    public function tv(): Response
    {
        $court = fn ($p) => $p ? trim($p->prenom.' '.mb_substr($p->nom, 0, 1).'.') : null;
        $appels = RendezVous::duJour()->whereNotNull('appele_le')->with(['docteur:id,name,salle', 'patient:id,nom,prenom'])
            ->latest('appele_le')->limit(4)->get()
            ->map(fn ($r) => ['id' => $r->id, 'numero_appel' => $r->numero_appel, 'appele_le' => $r->appele_le, 'patient' => $court($r->patient), 'docteur' => $r->docteur?->only(['name', 'salle'])]);
        $attente = RendezVous::duJour()->where('statut', StatutRdv::Arrive->value)
            ->orderByDesc('urgence')->orderBy('arrive_le')->get(['patient_id', 'numero_appel'])
            ->unique('patient_id')->pluck('numero_appel')->unique()->take(8)->values();

        $enCours = RendezVous::duJour()->where('statut', StatutRdv::EnCours->value)->with('patient:id,nom,prenom')->get()->keyBy('docteur_id');
        $docteurs = User::docteurs()->get(['id', 'name', 'salle', 'etat']);
        $noms = \App\Models\Salle::where('actif', true)->orderBy('ordre')->orderBy('nom')->pluck('nom')
            ->merge($docteurs->pluck('salle')->filter())->unique()->values();
        $salles = $noms->map(function ($nom) use ($docteurs, $enCours, $court) {
            $docs = $docteurs->where('salle', $nom);
            $occupe = $docs->first(fn ($d) => $enCours->has($d->id));
            $rdv = $occupe ? $enCours->get($occupe->id) : null;

            return [
                'nom' => $nom,
                'docteur' => ($occupe ?? $docs->first())?->name,
                'patient' => $court($rdv?->patient),
                'numero' => $rdv?->numero_appel,
                'etat' => $rdv ? 'occupee' : ($docs->contains(fn ($d) => $d->etat?->value === 'pret') ? 'libre' : 'fermee'),
            ];
        });

        return Inertia::render('public/tv', ['appels' => $appels, 'attente' => $attente, 'salles' => $salles, 'cabinet' => config('cabinet.nom')]);
    }

    public function recherchePatients(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            Patient::where('statut_fiche', 'validee')->recherche($request->string('q')->toString())
                ->with('dossier:id,patient_id,numero')->limit(8)->get(['id', 'nom', 'prenom', 'telephone', 'ville', 'cin', 'date_naissance'])
        );
    }
}
