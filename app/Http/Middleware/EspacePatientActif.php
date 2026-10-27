<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mode local (ESPACE_PATIENT=false dans .env) : QR code et « Mon espace » désactivés en attendant l'accord de la CNDP. */
class EspacePatientActif
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('cabinet.espace_patient')) {
            return $next($request);
        }
        if ($request->user()) {
            return back()->with('error', 'Carte patient et QR code désactivés pour le moment (en attente de l’accord CNDP).');
        }

        return response()->view('espace-indisponible', [], 404);
    }
}
