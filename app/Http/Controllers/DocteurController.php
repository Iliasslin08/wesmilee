<?php

namespace App\Http\Controllers;

use App\Enums\EtatDocteur;
use App\Enums\StatutRdv;
use App\Models\RendezVous;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocteurController extends Controller
{
    public function patients(Request $request): Response
    {
        $user = $request->user();
        $jour = $request->input('jour', 'aujourdhui');
        $date = $jour === 'demain' ? now()->addDay() : now();

        $rdvs = RendezVous::duJour($date)->where('docteur_id', $user->id)
            ->whereNotIn('statut', [StatutRdv::Annule->value])
            ->with(['patient:id,nom,prenom,ville,allergies', 'patient.dossier:id,patient_id,numero', 'acte:id,libelle,dents'])
            ->orderByDesc('urgence')->orderBy('debut')->get()
            ->map(function (RendezVous $r) {
                $dossier = $r->patient->dossier;
                $labo = $dossier?->commandesLabo()->whereDate('date_pose', $r->debut->toDateString())->first();

                // Dernière séance terminée : qui l'a vu, ce qui a été fait, la consigne pour la suite
                $derniere = $dossier?->seances()->whereNotNull('fin')->with(['docteur:id,name', 'actes:id,seance_id,libelle,dents'])->first();

                return $r->toArray() + [
                    'statut_label' => $r->statut->label(),
                    'historique' => $derniere ? [
                        'date' => $derniere->debut->toIso8601String(),
                        'docteur' => $derniere->docteur?->name,
                        'fait' => $derniere->actes->map(fn ($a) => $a->libelle.($a->dents ? " ({$a->dents})" : ''))->implode(', '),
                        'suite' => $derniere->suite,
                    ] : null,
                    'labo' => $labo ? ['statut' => $labo->statut->value, 'label' => $labo->statut->label(), 'travail' => $labo->travail] : null,
                ];
            });

        // Plan de la semaine : les 7 prochains jours, groupés par jour
        $semaine = [];
        if ($jour === 'semaine') {
            $semaine = RendezVous::where('docteur_id', $user->id)
                ->whereBetween('debut', [now()->startOfDay(), now()->addDays(6)->endOfDay()])
                ->where('statut', '!=', StatutRdv::Annule->value)
                ->with(['patient:id,nom,prenom,ville,allergies', 'patient.dossier:id,patient_id,numero'])
                ->orderBy('debut')->get()
                ->groupBy(fn ($r) => $r->debut->toDateString())
                ->map(fn ($liste, $d) => [
                    'date' => $d,
                    'label' => ucfirst(\Illuminate\Support\Carbon::parse($d)->translatedFormat('l j F')),
                    'rdvs' => $liste->map(fn ($r) => $r->toArray() + ['statut_label' => $r->statut->label()])->values(),
                ])->values();
        }

        $fini = fn ($r) => in_array($r['statut'], [StatutRdv::Termine->value, StatutRdv::Absent->value], true);

        return Inertia::render('docteur/patients', [
            'jour' => $jour,
            'dateLabel' => ucfirst($date->translatedFormat('l j F')),
            'rdvs' => $rdvs->reject($fini)->values(),
            'termines' => $rdvs->filter($fini)->values(),
            'etat' => $user->etat?->value,
            'semaine' => $semaine,
        ]);
    }

    public function etat(Request $request): RedirectResponse
    {
        $data = $request->validate(['etat' => ['required', Rule::enum(EtatDocteur::class)]]);
        $request->user()->changerEtat(EtatDocteur::from($data['etat']));
        $this->ok('Votre état : '.EtatDocteur::from($data['etat'])->label());

        return back();
    }
}
