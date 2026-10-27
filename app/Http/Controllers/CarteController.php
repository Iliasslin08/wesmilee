<?php

namespace App\Http\Controllers;

use App\Enums\StatutRdv;
use App\Models\Patient;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Carte patient : photo + n° de dossier + nom + QR code.
 * Le QR ouvre la page « Mon dossier » du patient (toujours à jour, sans radios),
 * protégée par un code : les 4 derniers chiffres de son téléphone.
 */
class CarteController extends Controller
{
    /** Carte à imprimer (format carte bancaire 85,6 × 54 mm). */
    public function imprimer(Patient $patient): View
    {
        $lien = $patient->lienCarte();
        $qr = (new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd)))->writeString($lien);

        return view('carte', [
            'patient' => $patient,
            'dossier' => $patient->dossier,
            'photo' => $patient->photoCarte(),
            'qr' => preg_replace('/<\?xml.*?\?>/', '', $qr),
            'lien' => $lien,
        ]);
    }

    /** Nouveau lien : l'ancienne carte (perdue, volée) ne marche plus. */
    public function nouveauLien(Patient $patient): RedirectResponse
    {
        $patient->carteToken(nouveau: true);
        $this->ok('Nouveau QR code créé : réimprimez la carte, l’ancienne ne fonctionne plus.');

        return back();
    }

    /** Page publique ouverte par le QR code. */
    public function public(Request $request, string $token): View
    {
        $patient = Patient::where('carte_token', $token)->firstOrFail();
        if (! $request->session()->get("carte.$token")) {
            return view('carte-code', ['token' => $token, 'erreur' => null]);
        }

        // La carte ouvre aussi « Mon espace » (prendre un rendez-vous)
        $request->session()->put('espace.patient_id', $patient->id);

        return view('carte-dossier', self::donnees($patient));
    }

    /** Photo du patient sur sa page (seulement après le code). */
    public function photo(Request $request, string $token): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->session()->get("carte.$token"), 403);
        $photo = Patient::where('carte_token', $token)->firstOrFail()->photoCarte();
        abort_unless($photo && \Illuminate\Support\Facades\Storage::disk('local')->exists($photo->chemin), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($photo->chemin);
    }

    public function verifier(Request $request, string $token): RedirectResponse|View
    {
        $patient = Patient::where('carte_token', $token)->firstOrFail();
        $cle = 'carte:'.$token.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return view('carte-code', ['token' => $token, 'erreur' => \App\Support\Langue::t('Trop d’essais. Réessayez dans quelques minutes.', 'محاولات كثيرة. أعيدوا المحاولة بعد بضع دقائق.')]);
        }
        $attendu = substr(preg_replace('/\D/', '', (string) $patient->telephone), -4);
        if ($attendu === '' || preg_replace('/\D/', '', (string) $request->input('code')) !== $attendu) {
            RateLimiter::hit($cle, 300);

            return view('carte-code', ['token' => $token, 'erreur' => \App\Support\Langue::t('Code incorrect.', 'الرمز غير صحيح.')]);
        }
        RateLimiter::clear($cle);
        $request->session()->put("carte.$token", true);

        return redirect()->route('carte.public', $token);
    }

    /** Tout ce que le patient voit : séances, prochain RDV, labo, paiements. Jamais les radios. */
    public static function donnees(Patient $patient): array
    {
        $dossier = $patient->dossier;

        return [
            'patient' => $patient,
            'dossier' => $dossier,
            'photo' => $patient->photoCarte(),
            'prochains' => $patient->rendezVous()->with('docteur:id,name')
                ->where('debut', '>=', now()->startOfDay())->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value])
                ->orderBy('debut')->limit(5)->get(),
            'seances' => $dossier?->seances()->whereNotNull('fin')->with(['docteur:id,name', 'actes:id,seance_id,libelle,dents'])->limit(20)->get() ?? collect(),
            'actes' => $dossier?->actes()->where('statut', '!=', 'annule')->orderBy('id')->get() ?? collect(),
            'labo' => $dossier?->commandesLabo()->latest()->get() ?? collect(),
            'paiements' => $dossier?->paiements()->get() ?? collect(),
            'total' => $dossier?->totalConvenu() ?? 0,
            'paye' => $dossier?->totalRecu() ?? 0,
            'reste' => $dossier?->reste() ?? 0,
            'cabinet' => config('cabinet'),
        ];
    }
}
