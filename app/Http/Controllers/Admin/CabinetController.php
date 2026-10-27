<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Informations du cabinet et salles (espace du chef). */
class CabinetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cabinet', [
            'champs' => Parametre::CHAMPS,
            'valeurs' => collect(Parametre::CHAMPS)->mapWithKeys(fn ($l, $k) => [$k => (string) config("cabinet.$k", '')]),
            'salles' => Salle::orderBy('ordre')->orderBy('nom')->get()->map(fn (Salle $s) => $s->toArray() + [
                'docteurs' => User::docteurs()->where('salle', $s->nom)->pluck('name'),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(collect(Parametre::CHAMPS)->mapWithKeys(fn ($l, $k) => [$k => ['nullable', 'string', 'max:255']])->all());
        foreach ($data as $cle => $valeur) {
            Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
        }
        $this->ok('Informations du cabinet enregistrées.');

        return back();
    }

    public function storeSalle(Request $request): RedirectResponse
    {
        Salle::create($this->salle($request) + ['ordre' => (int) Salle::max('ordre') + 1]);
        $this->ok('Salle ajoutée.');

        return back();
    }

    public function updateSalle(Request $request, Salle $salle): RedirectResponse
    {
        $data = $this->salle($request, $salle);
        // si on renomme la salle, les docteurs suivent
        if ($data['nom'] !== $salle->nom) {
            User::where('salle', $salle->nom)->update(['salle' => $data['nom']]);
        }
        $salle->update($data);
        $this->ok('Salle mise à jour.');

        return back();
    }

    private function salle(Request $request, ?Salle $salle = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:50', Rule::unique('salles', 'nom')->ignore($salle?->id)],
            'equipement' => ['nullable', 'string', 'max:255'],
            'actif' => ['boolean'],
        ]);
    }
}
