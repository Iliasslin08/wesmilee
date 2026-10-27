<?php

namespace App\Http\Controllers;

use App\Enums\StatutRdv;
use App\Models\Acte;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\RdvAFixer;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Planificateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function index(Request $request): Response
    {
        [$date, $debut, $fin, $periode] = $this->periode($request);
        $q = trim((string) $request->input('q', ''));

        $docteurs = User::docteurs()->with(['disponibilites' => fn ($x) => $x->where('jour_semaine', $date->dayOfWeekIso)])
            ->get(['id', 'name', 'specialite', 'couleur', 'salle', 'taches_principales', 'fait_consultation', 'duree_rdv']);

        return Inertia::render('reception/agenda', [
            'date' => $date->toDateString(),
            'periode' => $periode,
            'vue' => $request->input('vue', 'tableau'),
            'docteurFiltre' => $request->integer('docteur') ?: null,
            'q' => $q,
            'dateLabel' => $periode === 'semaine'
                ? 'Semaine du '.$debut->translatedFormat('j F').' au '.$fin->translatedFormat('j F Y')
                : ucfirst($date->translatedFormat('l j F Y')),
            'docteurs' => $docteurs,
            'rdvs' => $this->rdvs($request, $debut, $fin, $q),
            'aFixer' => RdvAFixer::where('statut', 'en_attente')
                ->with(['patient:id,nom,prenom,telephone', 'patient.dossier:id,patient_id,numero', 'docteur:id,name', 'demandePar:id,name'])
                ->oldest()->get(),
        ]);
    }

    /** Planning imprimable (en cas de coupure d'internet ou d'électricité). */
    public function imprimer(Request $request): \Illuminate\View\View
    {
        [$date, $debut, $fin, $periode] = $this->periode($request);
        $rdvs = $this->rdvs($request, $debut, $fin, '');

        return view('planning', [
            'titre' => $periode === 'semaine'
                ? 'Semaine du '.$debut->translatedFormat('j F').' au '.$fin->translatedFormat('j F Y')
                : ucfirst($date->translatedFormat('l j F Y')),
            'parJour' => $rdvs->groupBy(fn ($r) => $r->debut->toDateString()),
            'cabinet' => config('cabinet'),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: string} */
    private function periode(Request $request): array
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        $periode = $request->input('periode') === 'semaine' ? 'semaine' : 'jour';

        return $periode === 'semaine'
            ? [$date, $date->copy()->startOfWeek(), $date->copy()->endOfWeek(), $periode]
            : [$date, $date->copy()->startOfDay(), $date->copy()->endOfDay(), $periode];
    }

    private function rdvs(Request $request, Carbon $debut, Carbon $fin, string $q)
    {
        $req = RendezVous::whereNotIn('statut', [StatutRdv::Annule->value])
            ->with(['patient:id,nom,prenom,ville,telephone,fiche_a_completer', 'patient.dossier:id,patient_id,numero', 'docteur:id,name,couleur,salle'])
            ->when($request->integer('docteur'), fn ($x, $id) => $x->where('docteur_id', $id));

        // Recherche d'un patient au téléphone : tous ses rendez-vous à venir (et le dernier passé)
        if ($q !== '') {
            return $req->whereHas('patient', fn ($p) => $p->recherche($q))
                ->where('debut', '>=', now()->subDays(30)->startOfDay())
                ->orderBy('debut')->limit(60)->get();
        }

        $rdvs = $req->whereBetween('debut', [$debut, $fin])->orderBy('debut')->get();
        // Venu sans RDV / en urgence alors qu'il a un RDV plus tard : on l'affiche pour pouvoir le supprimer
        $sansRdv = $rdvs->where('sans_rdv', true);
        if ($sansRdv->isNotEmpty()) {
            $futurs = RendezVous::whereIn('patient_id', $sansRdv->pluck('patient_id'))->where('debut', '>', $fin)
                ->where('statut', StatutRdv::Planifie->value)->with('docteur:id,name')->orderBy('debut')->get()->groupBy('patient_id');
            foreach ($sansRdv as $r) {
                $r->setAttribute('autres_rdv', ($futurs[$r->patient_id] ?? collect())->map(fn ($f) => ['id' => $f->id, 'debut' => $f->debut->toIso8601String(), 'mission' => $f->mission, 'docteur' => $f->docteur?->name])->values());
            }
        }

        return $rdvs;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required_without:nouveau', 'nullable', 'exists:patients,id'],
            // Nouveau patient au téléphone : juste nom, prénom, téléphone. Le reste sur la tablette le jour J.
            'nouveau' => ['nullable', 'array'],
            'nouveau.nom' => ['required_with:nouveau', 'string', 'max:100'],
            'nouveau.prenom' => ['required_with:nouveau', 'string', 'max:100'],
            'nouveau.telephone' => ['required_with:nouveau', 'string', 'max:30'],
            'creer_quand_meme' => ['boolean'],
            'a_fixer_id' => ['nullable', 'exists:rdv_a_fixer,id'],
            'docteur_id' => ['required', 'exists:users,id'],
            'acte_id' => ['nullable', 'exists:actes,id'],
            'mission' => ['required_without:acte_id', 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'heure' => ['required', 'date_format:H:i'],
            'duree' => ['required', 'integer', 'min:10', 'max:480'],
            'forcer' => ['boolean'],
        ]);

        $rdv = DB::transaction(function () use ($data, $request) {
            if (! empty($data['nouveau'])) {
                $data['patient_id'] = $this->patientRapide($data['nouveau'], $request)->id;
            }
            $rdv = Planificateur::creer($data, $request->user());
            if (! empty($data['a_fixer_id'])) {
                RdvAFixer::whereKey($data['a_fixer_id'])->update(['statut' => 'fixe', 'rendez_vous_id' => $rdv->id]);
            }

            return $rdv;
        });

        $this->ok("Rendez-vous le {$rdv->debut->translatedFormat('d/m à H:i')} avec {$rdv->docteur->name}.");

        return back();
    }

    /** Patient qui appelle pour la 1re fois : fiche minimale + dossier, complétée à son arrivée. */
    private function patientRapide(array $n, Request $request): Patient
    {
        $donnees = ['nom' => mb_strtoupper(trim($n['nom'])), 'prenom' => trim($n['prenom']), 'telephone' => trim($n['telephone'])];
        $doublons = Patient::doublonsPossibles($donnees);
        if ($doublons->isNotEmpty() && ! $request->boolean('creer_quand_meme')) {
            $d = $doublons->first();
            throw ValidationException::withMessages([
                'nouveau.nom' => "Ce patient existe peut-être déjà : {$d['nom_complet']}".
                    (isset($d['dossier']['numero']) ? " (dossier n° {$d['dossier']['numero']})" : '').
                    '. Cherchez-le plutôt, ou cochez « créer quand même ».',
            ]);
        }
        $patient = Patient::create($donnees + ['fiche_a_completer' => true, 'statut_fiche' => 'validee', 'valide_par' => $request->user()->id, 'valide_le' => now()]);
        Dossier::create(['patient_id' => $patient->id, 'cree_par' => $request->user()->id]);

        return $patient;
    }

    /** Créneaux libres d'un docteur (boutons cliquables dans la prise de RDV). */
    public function creneaux(Request $request): JsonResponse
    {
        $data = $request->validate(['docteur_id' => ['required', 'exists:users,id'], 'date' => ['required', 'date'], 'duree' => ['nullable', 'integer', 'min:10', 'max:480']]);

        return response()->json(Planificateur::creneaux(User::findOrFail($data['docteur_id']), Carbon::parse($data['date']), (int) ($data['duree'] ?? 30)));
    }

    /**
     * Prochain rendez-vous depuis le dossier (docteur ou réception) :
     * soit on fixe la date tout de suite, soit on laisse la réception la fixer avec le patient.
     */
    public function prochain(Request $request, Dossier $dossier): RedirectResponse
    {
        abort_if($request->user()->passeParLeChef(), 403, 'Après une consultation, le RDV est fixé par la finance après le devis.');
        $data = $request->validate(self::reglesProchain());
        $message = self::enregistrerProchain($data, $dossier, $request->user());
        $this->ok($message);

        return back();
    }

    public static function reglesProchain(string $p = ''): array
    {
        return [
            "{$p}mode" => ['required', 'in:fixer,reception'],
            "{$p}docteur_id" => ['required', 'exists:users,id'],
            "{$p}acte_id" => ['nullable', 'exists:actes,id'],
            "{$p}mission" => ['required', 'string', 'max:255'],
            "{$p}notes" => ['nullable', 'string', 'max:1000'],
            "{$p}date" => ["required_if:{$p}mode,fixer", 'nullable', 'date'],
            "{$p}heure" => ["required_if:{$p}mode,fixer", 'nullable', 'date_format:H:i'],
            "{$p}duree" => ['nullable', 'integer', 'min:10', 'max:480'],
            "{$p}forcer" => ['boolean'],
        ];
    }

    /** @return string message de confirmation */
    public static function enregistrerProchain(array $d, Dossier $dossier, User $par, string $cle = 'heure'): string
    {
        // Le travail demandé est toujours dans « À faire » du dossier
        $d['acte_id'] = $dossier->acteDemande($d['mission'], isset($d['acte_id']) ? (int) $d['acte_id'] : null, $par->id)->id;
        if ($d['mode'] === 'fixer') {
            $rdv = Planificateur::creer([
                'patient_id' => $dossier->patient_id, 'docteur_id' => $d['docteur_id'], 'acte_id' => $d['acte_id'] ?? null,
                'mission' => $d['mission'], 'date' => $d['date'], 'heure' => $d['heure'], 'duree' => $d['duree'] ?? 30, 'forcer' => $d['forcer'] ?? false,
            ], $par, $cle);

            return "Prochain rendez-vous le {$rdv->debut->translatedFormat('d/m à H:i')} avec {$rdv->docteur->name}.";
        }

        $af = RdvAFixer::create([
            'patient_id' => $dossier->patient_id, 'docteur_id' => $d['docteur_id'], 'acte_id' => $d['acte_id'] ?? null,
            'mission' => $d['mission'], 'notes' => $d['notes'] ?? null, 'demande_par' => $par->id,
        ]);

        return "Demande envoyée à la réception : RDV « {$af->mission} » avec {$af->docteur?->name} à fixer.";
    }

    public function retirerAFixer(RdvAFixer $aFixer): RedirectResponse
    {
        $aFixer->update(['statut' => 'annule']);
        $this->ok('Demande retirée.');

        return back();
    }

    public function modifier(Request $request, RendezVous $rdv): RedirectResponse
    {
        $this->autoriser($request, $rdv);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'heure' => ['required', 'date_format:H:i'],
            'duree' => ['required', 'integer', 'min:10', 'max:480'],
            'mission' => ['required', 'string', 'max:255'],
            'docteur_id' => ['nullable', 'exists:users,id'],
            'forcer' => ['boolean'],
        ]);
        $docteurId = $request->user()->hasRole(\App\Enums\Role::Reception) && ! empty($data['docteur_id']) ? (int) $data['docteur_id'] : $rdv->docteur_id;
        $debut = Carbon::parse($data['date'].' '.$data['heure']);
        $fin = $debut->copy()->addMinutes((int) $data['duree']);

        if (empty($data['forcer'])) {
            $conflit = RendezVous::where('docteur_id', $docteurId)->actifs()->whereKeyNot($rdv->id)
                ->where('debut', '<', $fin)->where('fin', '>', $debut)->exists();
            if ($conflit) {
                throw ValidationException::withMessages(['heure' => 'Ce créneau est déjà pris pour ce docteur.']);
            }
        }
        $rdv->update(['debut' => $debut, 'fin' => $fin, 'mission' => $data['mission'], 'docteur_id' => $docteurId]);
        if ($rdv->acte_id) {
            \App\Models\CommandeLabo::where('acte_id', $rdv->acte_id)->whereNotIn('statut', ['pose'])->update(['date_pose' => $debut->toDateString()]);
        }
        $this->ok("Rendez-vous déplacé au {$debut->translatedFormat('d/m à H:i')}.");

        return back();
    }

    private function autoriser(Request $request, RendezVous $rdv): void
    {
        $u = $request->user();
        abort_unless($u->hasRole(\App\Enums\Role::Reception) || $rdv->docteur_id === $u->id, 403);
        abort_if(in_array($rdv->statut, [StatutRdv::Termine, StatutRdv::EnCours], true), 422, 'Ce rendez-vous est déjà passé.');
    }

    /** Supprimer un rendez-vous de l'agenda (erreur, test…). L'historique des séances du dossier est gardé. */
    public function supprimer(Request $request, RendezVous $rdv): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->hasRole(\App\Enums\Role::Reception) || $rdv->docteur_id === $u->id, 403);
        abort_if($rdv->statut === StatutRdv::EnCours, 422, 'Séance en cours : terminez-la d\'abord.');
        $nom = $rdv->patient?->nom_complet;
        $rdv->delete();
        $this->ok("Rendez-vous de {$nom} supprimé.");

        return back();
    }

    public function annuler(Request $request, RendezVous $rdv): RedirectResponse
    {
        $this->autoriser($request, $rdv);
        $rdv->update(['statut' => StatutRdv::Annule]);
        $this->ok('Rendez-vous annulé.');

        return back();
    }

    /** Actes « À faire » d'un patient, pour choisir la mission du RDV. */
    public function actesPatient(int $patient): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            Acte::whereHas('dossier', fn ($q) => $q->where('patient_id', $patient))
                ->whereIn('statut', ['a_faire', 'en_cours'])->get(['id', 'libelle', 'dents', 'specialite', 'statut'])
        );
    }
}
