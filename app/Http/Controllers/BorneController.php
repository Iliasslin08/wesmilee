<?php

namespace App\Http\Controllers;

use App\Enums\StatutRdv;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\RendezVous;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Borne d'accueil (tablette sur pied à l'entrée) : le patient tape son n° de dossier ou son téléphone,
 * il est marqué « arrivé » et reçoit un ticket avec son n° d'appel (affiché ensuite sur l'écran TV).
 */
class BorneController extends Controller
{
    public function index(): View
    {
        return view('borne', ['etape' => 'saisie', 'cabinet' => config('cabinet')]);
    }

    public function arrivee(Request $request): View
    {
        $data = $request->validate(['numero' => ['required', 'string', 'max:30'], 'rdv_id' => ['nullable', 'integer']]);
        $saisie = preg_replace('/\D/', '', $data['numero']);
        $vue = ['cabinet' => config('cabinet'), 'numero' => $saisie];

        $patients = $this->patients($saisie);
        $rdvs = RendezVous::duJour()->whereIn('patient_id', $patients->pluck('id'))
            ->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value])
            ->with(['patient', 'patient.dossier', 'docteur:id,name,salle'])->orderBy('debut')->get()
            ->unique('patient_id')->values();

        if ($patients->isEmpty()) {
            return view('borne', $vue + ['etape' => 'inconnu']);
        }
        if ($rdvs->isEmpty()) {
            // déjà venu aujourd'hui (en séance / terminé) : pas de 2e ticket
            if (RendezVous::duJour()->whereIn('patient_id', $patients->pluck('id'))->where('statut', StatutRdv::EnCours->value)->exists()) {
                return view('borne', $vue + ['etape' => 'sans_rdv']);
            }

            return view('borne', $vue + ['etape' => 'sans_rdv', 'patients' => $patients]);
        }
        // Même téléphone pour plusieurs personnes (famille) : le patient choisit son prénom
        if ($rdvs->count() > 1 && empty($data['rdv_id'])) {
            return view('borne', $vue + ['etape' => 'choix', 'rdvs' => $rdvs]);
        }
        $rdv = ! empty($data['rdv_id']) ? $rdvs->firstWhere('id', (int) $data['rdv_id']) : $rdvs->first();
        abort_unless($rdv, 404);

        SalleAttenteController::marquerArrive($rdv);

        return $this->ticket($rdv, $vue);
    }

    /** Écran « Première visite » : nom, prénom, téléphone. */
    public function premiere(): View
    {
        return view('borne', ['etape' => 'premiere', 'cabinet' => config('cabinet')]);
    }

    /** Première visite (nouveau patient) : fiche minimale + consultation sans RDV → ticket. */
    public function enregistrerPremiere(Request $request): View
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:20', 'regex:/^[\d\s+.\-]{8,20}$/'],
        ]);
        $tel = preg_replace('/\D/', '', $data['telephone']);
        $vue = ['cabinet' => config('cabinet'), 'numero' => $tel];

        // déjà connu (même téléphone + même nom) : on ne crée pas de doublon
        $patient = $this->patients($tel)->first(fn ($p) => mb_strtolower(trim($p->nom)) === mb_strtolower(trim($data['nom'])));
        if (! $patient) {
            $patient = Patient::create([
                'nom' => mb_strtoupper(trim($data['nom'])),
                'prenom' => \Illuminate\Support\Str::title(mb_strtolower(trim($data['prenom']))),
                'telephone' => trim($data['telephone']),
                'fiche_a_completer' => true, 'statut_fiche' => 'validee', 'valide_le' => now(),
            ]);
            Dossier::create(['patient_id' => $patient->id]);
        }

        return $this->consultation($patient, $vue);
    }

    /** Patient connu sans RDV aujourd'hui qui vient pour une consultation. */
    public function sansRendezVous(Request $request): View
    {
        $data = $request->validate(['numero' => ['required', 'string', 'max:30'], 'patient_id' => ['required', 'integer']]);
        $saisie = preg_replace('/\D/', '', $data['numero']);
        $patient = $this->patients($saisie)->firstWhere('id', (int) $data['patient_id']);
        abort_unless($patient, 404);

        return $this->consultation($patient, ['cabinet' => config('cabinet'), 'numero' => $saisie]);
    }

    /** Consultation sans RDV : ajoutée à la salle d'attente (la réception choisit le docteur). */
    private function consultation(Patient $patient, array $vue): View
    {
        $rdv = RendezVous::duJour()->where('patient_id', $patient->id)
            ->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value])->orderBy('debut')->first();
        $rdv ??= RendezVous::create([
            'patient_id' => $patient->id, 'mission' => 'Consultation',
            'debut' => now(), 'fin' => now()->addMinutes(30), 'sans_rdv' => true,
            'statut' => StatutRdv::Arrive, 'arrive_le' => now(), 'numero_appel' => SalleAttenteController::prochainNumero(),
        ]);
        SalleAttenteController::marquerArrive($rdv);

        return $this->ticket($rdv, $vue);
    }

    private function ticket(RendezVous $rdv, array $vue): View
    {
        $rdv->refresh()->load(['patient', 'patient.dossier', 'docteur:id,name,salle']);
        $avant = RendezVous::duJour()->where('statut', StatutRdv::Arrive->value)->where('docteur_id', $rdv->docteur_id)
            ->where('id', '!=', $rdv->id)->where('arrive_le', '<', $rdv->arrive_le)->count();

        return view('borne', $vue + ['etape' => 'ticket', 'rdv' => $rdv, 'avant' => $avant]);
    }

    /** N° de dossier (ou ancien n° papier), sinon téléphone (9 derniers chiffres). */
    private function patients(string $saisie)
    {
        if ($saisie === '') {
            return collect();
        }
        $dossier = Dossier::where('numero', $saisie)->orWhere('ancien_numero_papier', $saisie)->first();
        if ($dossier && strlen($saisie) < 8) {
            return Patient::whereKey($dossier->patient_id)->get();
        }
        if (strlen($saisie) < 8) {
            return collect();
        }
        $fin = substr($saisie, -9);

        return Patient::where('telephone', 'like', '%'.substr($fin, -4).'%')->get()
            ->filter(fn ($p) => substr(preg_replace('/\D/', '', (string) $p->telephone), -9) === $fin)->values();
    }
}
