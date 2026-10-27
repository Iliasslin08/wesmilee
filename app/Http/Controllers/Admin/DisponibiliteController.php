<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Horaires (« feu vert ») de chaque docteur. Le docteur peut modifier les siens. */
class DisponibiliteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $docteurs = $user->isAdmin()
            ? User::docteurs()->with('disponibilites')->get(['id', 'name', 'specialite'])
            : User::whereKey($user->id)->with('disponibilites')->get(['id', 'name', 'specialite']);

        return Inertia::render('admin/disponibilites', ['docteurs' => $docteurs]);
    }

    public function update(Request $request, User $docteur): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->id === $docteur->id, 403);
        abort_unless($docteur->role === Role::Docteur, 422);

        $data = $request->validate([
            'plages' => ['array'],
            'plages.*.jour_semaine' => ['required', 'integer', 'between:1,7'],
            'plages.*.heure_debut' => ['required', 'date_format:H:i'],
            'plages.*.heure_fin' => ['required', 'date_format:H:i', 'after:plages.*.heure_debut'],
        ]);

        $docteur->disponibilites()->delete();
        foreach ($data['plages'] ?? [] as $p) {
            $docteur->disponibilites()->create($p);
        }
        $this->ok("Horaires de {$docteur->name} enregistrés.");

        return back();
    }
}
