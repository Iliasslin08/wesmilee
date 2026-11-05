<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Paiement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function impayes(): Response
    {
        $dossiers = Dossier::query()
            ->select('dossiers.*')
            ->selectSub(DB::table('actes')->whereColumn('actes.dossier_id', 'dossiers.id')->where('statut', '!=', 'annule')->whereNull('deleted_at')->selectRaw('coalesce(sum(prix_convenu),0)'), 'convenu')
            ->selectSub(DB::table('paiements')->whereColumn('paiements.dossier_id', 'dossiers.id')->selectRaw('coalesce(sum(montant),0)'), 'recu')
            ->with('patient:id,nom,prenom,telephone,ville')
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'numero' => $d->numero, 'patient' => $d->patient,
                'convenu' => (float) $d->convenu, 'recu' => (float) $d->recu, 'reste' => round($d->convenu - $d->recu, 2)])
            ->filter(fn ($d) => $d['reste'] > 0)->sortByDesc('reste')->values();

        return Inertia::render('finance/impayes', [
            'dossiers' => $dossiers,
            'total' => $dossiers->sum('reste'),
            'encaisseAujourdhui' => (float) Paiement::whereDate('date', today())->sum('montant'),
        ]);
    }

    public function store(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:1'],
            'methode' => ['required', 'in:especes,carte,cheque,virement'],
            'date' => ['required', 'date'],
            'facture_a' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $paiement = $dossier->paiements()->create($data + ['encaisse_par' => $request->user()->id]);
        $paiement->update(['reste_apres' => $dossier->reste()]);
        // Le patient le voit dans « Mon espace » (et par SMS s'il a accepté)
        if ($dossier->patient) {
            $reste = $paiement->reste_apres;
            \App\Services\Messagerie::envoyer($dossier->patient, 'paiement', 'Paiement reçu',
                'Nous avons bien reçu votre paiement de '.number_format($paiement->montant, 0, ',', ' ').' DH (reçu n° '.$paiement->numero_recu.'). '
                .($reste > 0 ? 'Reste à payer : '.number_format($reste, 0, ',', ' ').' DH.' : 'Votre traitement est soldé. Merci !'),
                $request->user()->id);
        }
        $this->ok("Reçu n° {$paiement->numero_recu} enregistré.");
        // le reçu est rempli automatiquement : on propose de l'imprimer tout de suite
        Inertia::flash('recu', ['id' => $paiement->id, 'numero' => $paiement->numero_recu]);

        return back();
    }

    /** Reçu imprimable (même présentation que le carnet papier). */
    public function recu(Paiement $paiement): View
    {
        $paiement->load('dossier.patient', 'encaissePar');
        $dejaPaye = (float) Paiement::where('dossier_id', $paiement->dossier_id)->where('id', '<', $paiement->id)->sum('montant');

        return view('recu', [
            'p' => $paiement,
            'cabinet' => config('cabinet'),
            'dejaPaye' => $dejaPaye,
            'totalTraitement' => $dejaPaye + $paiement->montant + $paiement->reste_apres,
            'enLettres' => \App\Support\MontantEnLettres::dirhams($paiement->montant),
        ]);
    }

    /** Feuille de soins remise au patient pour sa mutuelle (le patient la suit dans « Mon espace »). */
    public function feuilleSoins(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'organisme' => ['required', 'in:'.implode(',', array_keys(\App\Models\Patient::MUTUELLES))],
            'montant' => ['required', 'numeric', 'min:0'],
            'remise_le' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $dossier->feuillesSoins()->create($data + ['statut' => 'remise', 'cree_par' => $request->user()->id]);
        $this->ok('Feuille de soins notée dans le dossier.');

        return back();
    }

    public function feuilleSoinsStatut(Request $request, \App\Models\FeuilleSoins $feuille): RedirectResponse
    {
        $data = $request->validate(['statut' => ['required', 'in:'.implode(',', array_keys(\App\Models\FeuilleSoins::STATUTS))]]);
        $feuille->update($data);
        $this->ok('Statut mis à jour : '.\App\Models\FeuilleSoins::STATUTS[$data['statut']].'.');

        return back();
    }
}
