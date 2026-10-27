<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Ordonnance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;

class OrdonnanceController extends Controller
{
    public function store(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1', 'max:15'],
            'lignes.*.medicament' => ['required', 'string', 'max:255'],
            'lignes.*.posologie' => ['nullable', 'string', 'max:255'],
            'lignes.*.duree' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $o = $dossier->ordonnances()->create($data + ['docteur_id' => $request->user()->id, 'date' => today()]);
        $this->ok('Ordonnance enregistrée dans le dossier.');
        Inertia::flash('ordonnance', ['id' => $o->id]);

        return back();
    }

    /** Ordonnance imprimable, avec l'en-tête du cabinet. */
    public function imprimer(Ordonnance $ordonnance): View
    {
        return view('ordonnance', ['o' => $ordonnance->load('dossier.patient', 'docteur'), 'cabinet' => config('cabinet')]);
    }
}
