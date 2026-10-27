<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Usage : ->middleware('role:secretaire,docteur'). Le chef (admin) passe partout. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->actif) {
            Auth::logout();

            return redirect()->route('login');
        }

        // Utilisateurs, salles et paramètres : réservés au compte développeur (pas même la chef).
        if ($roles === ['developpeur'] && $user->role !== Role::Developpeur) {
            abort(403, "Cette partie de la plateforme n'est pas dans votre rôle.");
        }

        $autorises = array_map(fn ($r) => Role::from($r), $roles);
        if ($roles && ! $user->hasRole(...$autorises)) {
            abort(403, "Cette partie de la plateforme n'est pas dans votre rôle.");
        }

        return $next($request);
    }
}
