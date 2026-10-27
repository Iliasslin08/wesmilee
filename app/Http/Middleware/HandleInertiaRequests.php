<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'role' => $request->user()->role?->value,
                    'role_label' => $request->user()->role?->label(),
                    'chef_labo' => $request->user()->estChefLabo(),
                ] : null,
            ],
            'cabinet' => config('cabinet.nom'),
            'espacePatient' => (bool) config('cabinet.espace_patient'),
            'reglages' => [
                'inactiviteMinutes' => (int) config('cabinet.inactivite_minutes'),
                'seanceMaxMinutes' => (int) config('cabinet.seance_max_minutes'),
            ],
            // Séance ouverte du docteur connecté (bandeau de rappel sur toutes ses pages)
            'maSeance' => fn () => $request->user()?->role === \App\Enums\Role::Docteur
                ? \App\Models\Seance::where('docteur_id', $request->user()->id)->whereNull('fin')
                    ->with('dossier:id,patient_id,numero', 'dossier.patient:id,nom,prenom')->latest('debut')->first()
                    ?->only(['id', 'debut', 'dossier'])
                : null,
            'demandesRadio' => fn () => $request->user()?->role === \App\Enums\Role::Radio
                ? \App\Models\DemandeRadio::where('statut', 'en_attente')->count() : 0,
            // Nombre de fiches tablette en attente (badge du menu réception)
            'fichesEnAttente' => fn () => $request->user()?->hasRole(\App\Enums\Role::Reception)
                ? \App\Models\Patient::where('statut_fiche', 'a_valider')->count()
                : 0,
            // Prochains RDV demandés par les docteurs, date à fixer (badge Agenda de la réception)
            'rdvAFixer' => fn () => $request->user()?->role === \App\Enums\Role::Reception
                ? \App\Models\RdvAFixer::where('statut', 'en_attente')->count() : 0,
            // Plans proposés après consultation, à présenter au patient (finance / chef)
            'plansAValider' => fn () => $request->user()?->hasRole(\App\Enums\Role::Finance) ? \App\Models\PlanAValider::where('statut', 'en_attente')->count() : 0,
            // Nouveaux avis patients (badge du menu du chef)
            'avisNonLus' => fn () => $request->user()?->isAdmin() ? \App\Models\Avis::whereNull('lu_le')->count() : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
