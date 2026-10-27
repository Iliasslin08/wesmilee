<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogueActe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/catalogue', ['actes' => CatalogueActe::orderBy('libelle')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        CatalogueActe::create($this->donnees($request));
        $this->ok('Acte ajouté au catalogue.');

        return back();
    }

    public function update(Request $request, CatalogueActe $acte): RedirectResponse
    {
        $acte->update($this->donnees($request));
        $this->ok('Catalogue mis à jour.');

        return back();
    }

    private function donnees(Request $request): array
    {
        return $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'specialite' => ['nullable', 'string', 'max:100'],
            'prix_defaut' => ['nullable', 'numeric', 'min:0'],
            'duree_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'necessite_labo' => ['boolean'],
            'actif' => ['boolean'],
        ]);
    }
}
