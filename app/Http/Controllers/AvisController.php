<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use Inertia\Inertia;
use Inertia\Response;

/** Commentaires des patients : seul le chef les voit. */
class AvisController extends Controller
{
    public function index(): Response
    {
        $avis = Avis::with('patient:id,nom,prenom,telephone', 'patient.dossier:id,patient_id,numero')->latest()->limit(300)->get();
        $page = Inertia::render('admin/avis', ['avis' => $avis, 'total' => Avis::count()]);
        // Ouverts par le chef : ils ne sont plus « nouveaux » (le surlignage reste pour cette visite)
        Avis::whereNull('lu_le')->update(['lu_le' => now()]);

        return $page;
    }
}
