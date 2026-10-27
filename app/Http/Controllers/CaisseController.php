<?php

namespace App\Http\Controllers;

use App\Models\ClotureCaisse;
use App\Models\Paiement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Clôture de caisse du soir : total par méthode, espèces comptées, écart. */
class CaisseController extends Controller
{
    public const METHODES = ['especes' => 'Espèces', 'carte' => 'Carte', 'cheque' => 'Chèque', 'virement' => 'Virement'];

    public function index(Request $request): Response
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $paiements = Paiement::whereDate('date', $date)->with(['dossier:id,numero,patient_id', 'dossier.patient:id,nom,prenom', 'encaissePar:id,name'])
            ->orderBy('numero_recu')->get();
        $cloture = ClotureCaisse::whereDate('date', $date)->with('cloturePar:id,name')->first();

        return Inertia::render('finance/caisse', [
            'date' => $date,
            'dateLabel' => ucfirst(Carbon::parse($date)->translatedFormat('l j F Y')),
            'paiements' => $paiements,
            'totaux' => $this->totaux($paiements),
            'cloture' => $cloture,
            // paiements enregistrés après la clôture (à vérifier)
            'apresCloture' => $cloture ? $paiements->where('created_at', '>', $cloture->created_at)->count() : 0,
            'historique' => ClotureCaisse::with('cloturePar:id,name')->latest('date')->limit(10)->get(),
            'methodes' => self::METHODES,
        ]);
    }

    public function cloturer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'especes_comptees' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_if(ClotureCaisse::whereDate('date', $data['date'])->exists(), 422, 'Cette journée est déjà clôturée.');

        $paiements = Paiement::whereDate('date', $data['date'])->get();
        $totaux = $this->totaux($paiements);
        $ecart = isset($data['especes_comptees']) ? round($data['especes_comptees'] - $totaux['especes'], 2) : null;

        ClotureCaisse::create([
            'date' => $data['date'],
            'totaux' => $totaux,
            'total' => array_sum($totaux),
            'nb_paiements' => $paiements->count(),
            'especes_comptees' => $data['especes_comptees'] ?? null,
            'ecart' => $ecart,
            'note' => $data['note'] ?? null,
            'cloture_par' => $request->user()->id,
        ]);
        $this->ok($ecart ? 'Caisse clôturée avec un écart de '.number_format($ecart, 2, ',', ' ').' DH.' : 'Caisse clôturée.');

        return back();
    }

    private function totaux($paiements): array
    {
        return collect(self::METHODES)->map(fn ($l, $m) => round((float) $paiements->where('methode', $m)->sum('montant'), 2))->all();
    }
}
