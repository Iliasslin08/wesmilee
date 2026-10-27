<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutLabo;
use App\Enums\StatutRdv;
use App\Http\Controllers\Controller;
use App\Models\CommandeLabo;
use App\Models\Dossier;
use App\Models\Journal;
use App\Models\Paiement;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\Seance;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class TableauDeBordController extends Controller
{
    public function __invoke(): Response
    {
        $mois = [now()->startOfMonth(), now()->endOfMonth()];

        return Inertia::render('admin/tableau-de-bord', [
            'kpis' => [
                ['label' => "Patients aujourd'hui", 'valeur' => RendezVous::duJour()->whereNotIn('statut', [StatutRdv::Annule->value])->count()],
                ['label' => 'Sans rendez-vous (mois)', 'valeur' => RendezVous::whereBetween('debut', $mois)->where('sans_rdv', true)->count()],
                ['label' => 'Absences (mois)', 'valeur' => RendezVous::whereBetween('debut', $mois)->where('statut', StatutRdv::Absent->value)->count()],
                ['label' => 'Encaissé ce mois (DH)', 'valeur' => number_format((float) Paiement::whereBetween('date', $mois)->sum('montant'), 0, ',', ' ')],
                ['label' => 'Nouveaux dossiers (mois)', 'valeur' => Dossier::whereBetween('created_at', $mois)->count()],
                ['label' => 'Fiches à valider', 'valeur' => Patient::where('statut_fiche', 'a_valider')->count()],
                ['label' => 'Travaux labo en cours', 'valeur' => CommandeLabo::whereIn('statut', [StatutLabo::AFaire->value, StatutLabo::EnFabrication->value])->count()],
                ['label' => 'Séances ce mois', 'valeur' => Seance::whereBetween('debut', $mois)->count()],
            ],
            'docteurs' => User::docteurs()->get(['id', 'name', 'etat'])->map(fn (User $d) => [
                'name' => $d->name,
                'etat' => $d->etat?->label(),
                'seances' => Seance::where('docteur_id', $d->id)->whereBetween('debut', $mois)->count(),
                'minutes' => (int) Seance::where('docteur_id', $d->id)->whereBetween('debut', $mois)->sum('duree_minutes'),
            ]),
            // les actions de la développeuse restent invisibles pour le cabinet
            'journal' => Journal::with('user:id,name')
                ->when(auth()->user()?->role !== \App\Enums\Role::Developpeur, fn ($q) => $q->where(fn ($x) => $x->whereNull('user_id')
                    ->orWhereNotIn('user_id', User::where('role', \App\Enums\Role::Developpeur->value)->select('id'))))
                ->latest('id')->limit(30)->get(),
        ]);
    }
}
