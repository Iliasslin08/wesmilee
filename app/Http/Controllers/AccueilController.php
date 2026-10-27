<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/** Fiche d'accueil remplie par le patient sur la tablette de l'accueil. */
class AccueilController extends Controller
{
    public const ANTECEDENTS = [
        'diabete' => 'Diabète', 'hypertension' => 'Hypertension', 'cardiaque' => 'Maladie cardiaque',
        'anticoagulants' => 'Anticoagulants', 'asthme' => 'Asthme', 'enceinte' => 'Enceinte', 'fumeur' => 'Fumeur',
    ];

    /** Patient envoyé à la tablette par la réception (dossier pris au téléphone, fiche à compléter). */
    public const CLE_TABLETTE = 'tablette.patient_attendu';

    public function create(): Response
    {
        return Inertia::render('public/accueil', [
            'antecedents' => self::ANTECEDENTS,
            'attendu' => fn () => self::attendu()?->only(['id', 'nom', 'prenom', 'telephone']),
        ]);
    }

    private static function attendu(): ?Patient
    {
        $id = Cache::get(self::CLE_TABLETTE);

        return $id ? Patient::find($id) : null;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'sexe' => ['nullable', 'in:F,M'],
            'telephone' => ['required', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'antecedents' => ['array'],
            'antecedents.*' => ['string', 'in:'.implode(',', array_keys(self::ANTECEDENTS))],
            'antecedents_autres' => ['nullable', 'string', 'max:1000'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'traitements' => ['nullable', 'string', 'max:500'],
        ]);

        // Patient attendu (envoyé par la réception) : sa fiche complète directement son dossier.
        $attendu = self::attendu();
        if ($attendu && (int) $request->input('patient_attendu_id') === $attendu->id) {
            $attendu->update(collect($data)->filter(fn ($v) => filled($v))->all() + ['fiche_a_completer' => false]);
            Cache::forget(self::CLE_TABLETTE);
        } else {
            Patient::create($data + ['statut_fiche' => 'a_valider']);
        }

        return redirect()->route('accueil.merci', ['lang' => $request->input('langue') === 'ar' ? 'ar' : 'fr']);
    }

    public function merci(Request $request): Response
    {
        return Inertia::render('public/accueil-merci', ['lang' => $request->input('lang') === 'ar' ? 'ar' : 'fr']);
    }
}
