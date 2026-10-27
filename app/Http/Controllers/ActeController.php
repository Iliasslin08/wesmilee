<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatutActe;
use App\Models\Acte;
use App\Models\CatalogueActe;
use App\Models\Dossier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Les 3 casiers : À faire → En cours → Fait. */
class ActeController extends Controller
{
    public function store(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'catalogue_acte_id' => ['nullable', 'exists:catalogue_actes,id'],
            'libelle' => ['required_without:catalogue_acte_id', 'nullable', 'string', 'max:255'],
            'dents' => ['nullable', 'string', 'max:100'],
            'prix_convenu' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'statut' => ['nullable', 'in:a_faire,fait'],
            'fait_le' => ['nullable', 'date', 'before_or_equal:today'],
            'docteur_id' => ['nullable', 'exists:users,id'],
        ]);
        $user = $request->user();
        // Réception : acte « Fait » repris de l'ancien dossier papier (date et docteur d'origine, sans prix)
        if (($data['statut'] ?? null) === 'fait' && $user->role === Role::Reception) {
            $cat = isset($data['catalogue_acte_id']) ? CatalogueActe::find($data['catalogue_acte_id']) : null;
            $dossier->actes()->create([
                'catalogue_acte_id' => $cat?->id,
                'libelle' => trim(($data['libelle'] ?? null) ?: (string) $cat?->libelle),
                'specialite' => $cat?->specialite,
                'dents' => $data['dents'] ?? null,
                'prix_convenu' => 0, // déjà réglé sur papier : ne crée pas de reste à payer
                'notes' => trim('Ancien dossier papier'.(! empty($data['notes']) ? ' · '.$data['notes'] : '')),
                'statut' => StatutActe::Fait,
                'prevu_par' => $user->id,
                'realise_par' => $data['docteur_id'] ?? null,
                'fait_le' => $data['fait_le'] ?? now(),
            ]);
            $this->ok('Acte du dossier papier ajouté dans « Fait ».');

            return back();
        }
        // Acte écrit directement dans « Fait » (texte libre) par le docteur
        if (($data['statut'] ?? null) === 'fait') {
            abort_unless($request->user()->hasRole(Role::Docteur), 403);
            $seanceId = $dossier->seances()->where('docteur_id', $request->user()->id)->whereNull('fin')->value('id');
            $texte = trim(($data['libelle'] ?? '') ?: (string) CatalogueActe::find($data['catalogue_acte_id'] ?? 0)?->libelle);
            $acte = $dossier->acteFait($texte.(! empty($data['dents']) ? ' · '.trim($data['dents']) : ''), $request->user()->id, $seanceId);
            if (! empty($data['notes'])) {
                $acte->update(['notes' => $data['notes']]);
            }
            $this->ok('Acte ajouté dans « Fait ».');

            return back();
        }
        $cat = isset($data['catalogue_acte_id']) ? CatalogueActe::find($data['catalogue_acte_id']) : null;

        $dossier->actes()->create([
            'catalogue_acte_id' => $cat?->id,
            'libelle' => ($data['libelle'] ?? null) ?: $cat?->libelle,
            'specialite' => $cat?->specialite,
            'dents' => $data['dents'] ?? null,
            // le prix vient du catalogue ; seule la finance peut le changer
            'prix_convenu' => ($request->user()->hasRole(Role::Finance) ? ($data['prix_convenu'] ?? null) : null) ?? $cat?->prix_defaut ?? 0,
            'notes' => $data['notes'] ?? null,
            'statut' => StatutActe::AFaire,
            'prevu_par' => $request->user()->id,
        ]);
        if ($dossier->statut === 'en_suivi') {
            $dossier->update(['statut' => 'en_traitement']);
        }
        $this->ok('Acte ajouté dans « À faire ».');

        return back();
    }

    public function update(Request $request, Acte $acte): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'statut' => ['sometimes', Rule::enum(StatutActe::class)],
            'libelle' => ['sometimes', 'string', 'max:255'],
            'prix_convenu' => ['sometimes', 'numeric', 'min:0'],
            'dents' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if (array_key_exists('prix_convenu', $data) && ! $user->hasRole(Role::Finance)) {
            abort(403);
        }
        if (array_key_exists('libelle', $data)) {
            abort_unless($user->hasRole(Role::Docteur), 403);
        }
        if (array_key_exists('statut', $data)) {
            abort_unless($user->hasRole(Role::Docteur), 403);
            // « Fait » par erreur : on le remet à faire
            if ($acte->statut === StatutActe::Fait && $data['statut'] !== StatutActe::Fait->value) {
                $data += ['realise_par' => null, 'fait_le' => null, 'seance_id' => null];
            }
            if ($data['statut'] === StatutActe::Fait->value) {
                $data['realise_par'] = $user->id;
                $data['fait_le'] = now();
                $data['seance_id'] = $acte->dossier->seances()->where('docteur_id', $user->id)->whereNull('fin')->value('id') ?? $acte->seance_id;
            }
        }
        $acte->update($data);

        $dossier = $acte->dossier;
        if (! $dossier->actes()->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])->exists()) {
            $dossier->update(['statut' => 'en_suivi']);
        }

        return back();
    }

    public function destroy(Request $request, Acte $acte): RedirectResponse
    {
        if ($acte->statut === StatutActe::Fait) {
            // Acte « Fait » écrit par erreur : seul le docteur qui l'a fait (ou le chef) peut le supprimer
            $user = $request->user();
            abort_unless($user->isAdmin() || $acte->realise_par === $user->id || $acte->prevu_par === $user->id, 403, 'Seul le docteur qui a fait cet acte peut le supprimer.');
            $acte->delete();
            $this->ok('Acte supprimé.');

            return back();
        }
        $acte->update(['statut' => StatutActe::Annule]);
        $this->ok('Acte annulé.');

        return back();
    }
}
