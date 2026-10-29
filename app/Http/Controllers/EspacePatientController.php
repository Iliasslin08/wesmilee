<?php

namespace App\Http\Controllers;

use App\Enums\StatutRdv;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Planificateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * « Mon espace patient » (sans compte) : le patient voit son dossier et prend lui-même
 * un rendez-vous de consultation ou de contrôle, seulement sur les créneaux encore libres.
 */
class EspacePatientController extends Controller
{
    public const MOTIFS = ['consultation' => 'Consultation', 'controle' => 'Contrôle'];

    /** Jours ouverts à la réservation en ligne. */
    public const JOURS_MAX = 60;

    /** Délai minimum avant un rendez-vous pris en ligne (minutes). */
    public const DELAI_MIN = 60;

    public function accueil(Request $request): View|RedirectResponse
    {
        if ($this->patient($request)) {
            return redirect()->route('espace.index');
        }

        return view('espace-accueil', ['cabinet' => config('cabinet')]);
    }

    /** Patient déjà venu : téléphone + (date de naissance ou n° de dossier). */
    public function connexion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'telephone' => ['required', 'string', 'max:30'],
            'preuve' => ['required', 'string', 'max:30'],
        ], [], ['preuve' => 'date de naissance ou n° de dossier']);
        $cle = 'espace:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            return back()->withErrors(['telephone' => \App\Support\Langue::t('Trop d’essais. Réessayez dans quelques minutes.', 'محاولات كثيرة. أعيدوا المحاولة بعد بضع دقائق.')])->withInput();
        }
        $patient = $this->trouver($data['telephone'], $data['preuve']);
        if (! $patient) {
            RateLimiter::hit($cle, 300);

            return back()->withErrors(['telephone' => \App\Support\Langue::t('Aucun dossier ne correspond. Vérifiez le téléphone et la date de naissance (ou le n° de dossier), ou appelez le centre.', 'لا يوجد ملف مطابق. تحققوا من الهاتف وتاريخ الازدياد (أو رقم الملف)، أو اتصلوا بالمركز.')])->withInput();
        }
        RateLimiter::clear($cle);
        $request->session()->regenerate();
        $request->session()->put('espace.patient_id', $patient->id);

        return redirect()->route('espace.index');
    }

    /** Première visite : nom, prénom, téléphone → dossier « à confirmer » (fiche remplie sur la tablette le jour J). */
    public function nouveau(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'min:8', 'max:30'],
        ]);
        if ($this->parTelephone($data['telephone'])->isNotEmpty()) {
            return back()->withErrors(['nouveau' => \App\Support\Langue::t('Ce numéro a déjà un dossier chez nous : utilisez « J’ai déjà un dossier ».', 'هذا الرقم لديه ملف عندنا: اختاروا « عندي ملف ».')])->withInput();
        }
        $patient = Patient::create([
            'nom' => mb_strtoupper(trim($data['nom'])), 'prenom' => trim($data['prenom']), 'telephone' => trim($data['telephone']),
            'fiche_a_completer' => true, 'statut_fiche' => 'validee', 'valide_le' => now(),
        ]);
        Dossier::create(['patient_id' => $patient->id]);
        $request->session()->regenerate();
        $request->session()->put('espace.patient_id', $patient->id);

        return redirect()->route('espace.index');
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (! $patient = $this->patient($request)) {
            return redirect()->route('espace.accueil');
        }
        $prochain = $this->rdvAVenir($patient);

        return view('espace', CarteController::donnees($patient) + [
            'prochain' => $prochain,
            'motifs' => $this->motifsPour($patient),
            'jourMin' => now()->toDateString(),
            'jourMax' => now()->addDays(self::JOURS_MAX)->toDateString(),
        ]);
    }

    /** Créneaux libres (JSON) : uniquement ceux qu'aucun RDV (réception, téléphone, en ligne) n'occupe. */
    public function creneaux(Request $request): JsonResponse
    {
        abort_unless($patient = $this->patient($request), 403);
        $data = $request->validate([
            'motif' => ['required', 'in:'.implode(',', array_keys($this->motifsPour($patient)))],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(self::JOURS_MAX)->toDateString()],
        ]);

        $heures = array_keys($this->libres(Carbon::parse($data['date'])));
        // Jour fermé ou complet : on propose le prochain jour qui a encore de la place
        $prochain = null;
        if (! $heures) {
            for ($j = Carbon::parse($data['date'])->addDay(); $j->lte(now()->addDays(self::JOURS_MAX)); $j->addDay()) {
                if ($this->libres($j)) {
                    $prochain = $j->toDateString();
                    break;
                }
            }
        }

        return response()->json(['heures' => $heures, 'prochain' => $prochain]);
    }

    public function reserver(Request $request): RedirectResponse
    {
        abort_unless($patient = $this->patient($request), 403);
        $data = $request->validate([
            'motif' => ['required', 'in:'.implode(',', array_keys($this->motifsPour($patient)))],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(self::JOURS_MAX)->toDateString()],
            'heure' => ['required', 'date_format:H:i'],
        ]);
        if ($deja = $this->rdvAVenir($patient)) {
            return back()->withErrors(['heure' => \App\Support\Langue::t('Vous avez déjà un rendez-vous le ', 'لديكم موعد يوم ').$deja->debut->format('d/m H:i').\App\Support\Langue::t('. Pour le changer, appelez le centre.', '. للتغيير، اتصلوا بالمركز.')]);
        }
        // Le créneau a pu être pris entre-temps (réception, téléphone) : on revérifie
        $docteurs = $this->libres(Carbon::parse($data['date']))[$data['heure']] ?? [];
        if (! $docteurs) {
            return back()->withErrors(['heure' => \App\Support\Langue::t('Ce créneau vient d’être pris. Choisissez-en un autre.', 'تم حجز هذا الموعد للتو. اختاروا موعدا آخر.')])->withInput();
        }
        $docteur = $docteurs[0];
        Planificateur::creer([
            'patient_id' => $patient->id, 'docteur_id' => $docteur->id, 'mission' => self::MOTIFS[$data['motif']],
            'date' => $data['date'], 'heure' => $data['heure'], 'duree' => $docteur->duree_rdv ?: 30, 'en_ligne' => true,
        ], null);

        return redirect()->route('espace.index')->with('ok', \App\Support\Langue::t('Votre rendez-vous est réservé.', 'تم حجز موعدكم.'));
    }

    /** Commentaire du patient, visible seulement par le chef. */
    public function avis(Request $request): RedirectResponse
    {
        abort_unless($patient = $this->patient($request), 403);
        $data = $request->validate([
            'commentaire' => ['required', 'string', 'max:1000'],
        ], ['commentaire.required' => \App\Support\Langue::t('Écrivez votre commentaire.', 'اكتبوا ملاحظتكم.')]);
        if ($patient->avis()->where('created_at', '>=', now()->subDay())->count() >= 3) {
            return back()->withErrors(['avis' => \App\Support\Langue::t('Merci, vous avez déjà laissé un commentaire aujourd’hui.', 'شكرا، لقد تركتم ملاحظة اليوم.')]);
        }
        $patient->avis()->create($data);

        return redirect()->to(url()->previous().'#avis')->with('avis_ok', \App\Support\Langue::t('Merci pour votre commentaire !', 'شكرا على ملاحظتكم!'));
    }

    public function deconnexion(Request $request): RedirectResponse
    {
        $request->session()->forget('espace.patient_id');

        return redirect()->route('espace.accueil');
    }

    public function photo(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($patient = $this->patient($request), 403);
        $photo = $patient->photoCarte();
        abort_unless($photo && \Illuminate\Support\Facades\Storage::disk('local')->exists($photo->chemin), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($photo->chemin);
    }

    /* ---------------- outils ---------------- */

    private function patient(Request $request): ?Patient
    {
        $id = $request->session()->get('espace.patient_id');

        return $id ? Patient::with('dossier')->find($id) : null;
    }

    private function rdvAVenir(Patient $patient): ?RendezVous
    {
        return $patient->rendezVous()->with('docteur:id,name')->where('debut', '>=', now())
            ->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value])->orderBy('debut')->first();
    }

    /** Contrôle seulement pour un patient déjà soigné. */
    private function motifsPour(Patient $patient): array
    {
        $dejaVenu = $patient->dossier?->seances()->whereNotNull('fin')->exists();

        return $dejaVenu ? self::MOTIFS : ['consultation' => 'Consultation'];
    }

    /** Docteurs qui font les consultations / contrôles (sinon tous). */
    private function docteurs()
    {
        $d = User::docteurs()->where('fait_consultation', true)->get();

        return $d->isNotEmpty() ? $d : User::docteurs()->get();
    }

    /** @return array<string, User[]> heure → docteurs libres à cette heure */
    private function libres(Carbon $jour): array
    {
        $limite = now()->addMinutes(self::DELAI_MIN);
        $libres = [];
        foreach ($this->docteurs() as $d) {
            foreach (Planificateur::creneaux($d, $jour, $d->duree_rdv ?: 30) as $h) {
                if (Carbon::parse($jour->toDateString().' '.$h)->gte($limite)) {
                    $libres[$h][] = $d;
                }
            }
        }
        ksort($libres);

        return $libres;
    }

    private function chiffres(string $s): string
    {
        return preg_replace('/\D/', '', $s);
    }

    private function parTelephone(string $tel)
    {
        $fin = substr($this->chiffres($tel), -9);
        if (strlen($fin) < 8) {
            return collect();
        }

        return Patient::with('dossier')->where('telephone', 'like', '%'.substr($fin, -4).'%')->get()
            ->filter(fn ($p) => substr($this->chiffres((string) $p->telephone), -9) === $fin);
    }

    private function trouver(string $tel, string $preuve): ?Patient
    {
        $preuve = trim($preuve);
        $date = null;
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $f) {
            try {
                $date = Carbon::createFromFormat($f, $preuve)->toDateString();
                break;
            } catch (\Throwable) {
            }
        }

        return $this->parTelephone($tel)->first(fn ($p) => ($date && $p->date_naissance?->toDateString() === $date)
            || ($p->dossier && $this->chiffres((string) $p->dossier->numero) === $this->chiffres($preuve) && $this->chiffres($preuve) !== ''));
    }
}
