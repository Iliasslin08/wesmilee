<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UtilisateurController extends Controller
{
    public function index(): Response
    {
        // Colonne « Patients » : nombre de patients suivis par chaque personne
        $parDocteur = \App\Models\RendezVous::whereNotIn('statut', ['annule'])->selectRaw('docteur_id, count(distinct patient_id) as n')->groupBy('docteur_id')->pluck('n', 'docteur_id');
        $parLabo = \App\Models\CommandeLabo::whereNotNull('technicien_id')->selectRaw('technicien_id, count(distinct dossier_id) as n')->groupBy('technicien_id')->pluck('n', 'technicien_id');
        $laboEnCours = \App\Models\CommandeLabo::whereNotNull('technicien_id')->whereNull('archivee_le')->selectRaw('technicien_id, count(*) as n')->groupBy('technicien_id')->pluck('n', 'technicien_id');

        return Inertia::render('admin/utilisateurs', [
            'utilisateurs' => User::visibles()->orderBy('role')->orderBy('name')->get()
                ->map(fn (User $u) => $u->toArray() + [
                    'role' => $u->role?->value, 'role_label' => $u->role?->label(),
                    'nb_patients' => match ($u->role) {
                        Role::Docteur => (int) ($parDocteur[$u->id] ?? 0),
                        Role::Labo => (int) ($parLabo[$u->id] ?? 0),
                        default => null,
                    },
                    'travaux_en_cours' => $u->role === Role::Labo ? (int) ($laboEnCours[$u->id] ?? 0) : null,
                ]),
            'roles' => Role::options(),
            'salles' => \App\Models\Salle::where('actif', true)->orderBy('ordre')->orderBy('nom')->pluck('nom'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->donnees($request);
        User::create($data + ['email_verified_at' => now()]);
        $this->ok("Compte créé pour {$data['name']}.");

        return back();
    }

    /** Supprimer un compte (désactivé et caché ; ses travaux labo redeviennent « à attribuer »). */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === Role::Developpeur, 404);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');
        \App\Models\CommandeLabo::where('technicien_id', $user->id)->update(['technicien_id' => null]);
        $user->update(['actif' => false]);
        $user->delete();
        $this->ok("Compte de {$user->name} supprimé.");

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === Role::Developpeur, 404);
        $data = $this->donnees($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);
        $this->ok('Compte mis à jour.');

        return back();
    }

    private function donnees(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)->except([Role::Developpeur])],
            'telephone' => ['nullable', 'string', 'max:20'],
            'specialite' => ['nullable', 'string', 'max:100'],
            'couleur' => ['nullable', 'string', 'max:7'],
            'salle' => ['nullable', 'string', 'max:50'],
            'fait_consultation' => ['boolean'],
            'duree_rdv' => ['nullable', 'integer', 'min:5', 'max:480'],
            'assistante' => ['nullable', 'string', 'max:255'],
            'taches_principales' => ['nullable', 'string', 'max:1000'],
            'taches_secondaires' => ['nullable', 'string', 'max:1000'],
            'autres_taches' => ['nullable', 'string', 'max:1000'],
            'actif' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }
}
