<?php

namespace App\Http\Controllers;

use App\Enums\StatutLabo;
use App\Enums\StatutRdv;
use App\Models\CommandeLabo;
use App\Models\Dossier;
use App\Models\RendezVous;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LaboController extends Controller
{
    private const ORDRE = ['urgent' => 0, 'bientot' => 1, 'normal' => 2, 'en_attente' => 3, 'termine' => 4];

    public function index(Request $request): Response
    {
        $moi = $request->user();
        $chef = $moi->estChefLabo();
        // « historique » = la zone Archive du labo
        $historique = $request->boolean('historique');
        // La cheffe du labo voit tout (filtre par personne) ; les autres seulement leurs travaux
        $filtre = $chef ? $request->input('qui', 'tous') : 'moi';
        $avec = ['dossier:id,numero,patient_id', 'dossier.patient:id,nom,prenom,ville', 'parent:id,travail,created_at', 'technicien:id,name', 'envoyePar:id,name'];
        $commandes = CommandeLabo::with($avec)
            ->whereNotNull('acceptee_le')
            ->when($filtre === 'moi', fn ($q) => $q->where('technicien_id', $moi->id))
            ->when(is_numeric($filtre), fn ($q) => $q->where('technicien_id', (int) $filtre))
            ->when(! $historique, fn ($q) => $q->whereNull('archivee_le'))
            ->when($historique, fn ($q) => $q->whereNotNull('archivee_le')->latest('archivee_le')->limit(200))
            ->get()
            ->map(fn (CommandeLabo $c) => $c->toArray() + ['priorite' => $c->priorite(), 'statut_label' => $c->statut->label()])
            ->sortBy([fn ($a, $b) => self::ORDRE[$a['priorite']] <=> self::ORDRE[$b['priorite']], fn ($a, $b) => ($a['date_prete'] ?? $a['date_pose'] ?? '9999') <=> ($b['date_prete'] ?? $b['date_pose'] ?? '9999')])
            ->values();
        // Radios : la cheffe voit tout ; les autres seulement celles des patients de leurs travaux
        $dossiersMiens = $chef ? null : CommandeLabo::where('technicien_id', $moi->id)->pluck('dossier_id');

        return Inertia::render('labo/index', [
            'chef' => $chef,
            'commandes' => $commandes,
            // Demandes écrites par les docteurs / la finance, à accepter et confier
            'aAccepter' => $chef && ! $historique ? CommandeLabo::with($avec)->whereNull('acceptee_le')->oldest()->get() : [],
            'radios' => \App\Models\Radio::query()
                ->when($dossiersMiens !== null, fn ($q) => $q->whereIn('dossier_id', $dossiersMiens))
                ->when(! $historique, fn ($q) => $q->whereNull('archivee_le')->orderByRaw('vue_labo_le is not null')->latest())
                ->when($historique, fn ($q) => $q->whereNotNull('archivee_le')->latest('archivee_le'))
                ->with(['dossier:id,numero,patient_id', 'dossier.patient:id,nom,prenom,ville', 'documents:id,radio_id,nom_original', 'fairePar:id,name'])
                ->limit(100)->get()
                ->map(fn ($r) => $r->toArray() + ['type_label' => \App\Models\Radio::TYPES[$r->type] ?? $r->type]),
            'historique' => $historique,
            'qui' => $filtre,
            'equipe' => $chef ? \App\Models\User::equipeLabo()->get(['id', 'name', 'taches_principales', 'taches_secondaires']) : [],
            'statuts' => collect(StatutLabo::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    /** La cheffe du labo accepte une demande et la confie, avec la date où elle doit être prête. */
    public function accepter(Request $request, CommandeLabo $commande): RedirectResponse
    {
        abort_unless($request->user()->estChefLabo(), 403);
        $data = $request->validate([
            'travail' => ['required', 'string', 'max:255'],
            'technicien_id' => ['required', Rule::exists('users', 'id')->where('role', \App\Enums\Role::Labo->value)],
            'date_prete' => ['required', 'date'],
        ], [], ['technicien_id' => 'personne du labo', 'date_prete' => 'date où le travail doit être prêt']);
        $commande->update($data + ['acceptee_le' => now(), 'acceptee_par' => $request->user()->id]);
        $this->ok("Demande acceptée et confiée à {$commande->technicien->name}, prête pour le {$commande->date_prete->format('d/m/Y')}.");

        return back();
    }

    /** Seule la cheffe du labo, ou la personne qui fait le travail, peut le modifier. */
    private function autoriser(Request $request, CommandeLabo $commande): void
    {
        $u = $request->user();
        abort_unless($u->estChefLabo() || $commande->technicien_id === $u->id, 403);
    }

    /** Commande prête / livrée : le labo la range dans l'archive. */
    public function archiver(Request $request, CommandeLabo $commande): RedirectResponse
    {
        $this->autoriser($request, $commande);
        abort_unless(in_array($commande->statut, [StatutLabo::Pret, StatutLabo::Livre, StatutLabo::Pose], true), 422, 'Seule une commande prête peut être archivée.');
        $commande->update(['archivee_le' => now()]);
        $this->ok("« {$commande->travail} » rangé dans l'archive.");

        return back();
    }

    /** Ressortir une commande de l'archive. */
    public function desarchiver(Request $request, CommandeLabo $commande): RedirectResponse
    {
        $this->autoriser($request, $commande);
        $commande->update(['archivee_le' => null]);
        $this->ok("« {$commande->travail} » est de nouveau dans les commandes en cours.");

        return back();
    }

    /** Ressortir une radio de l'archive. */
    public function radioDesarchiver(\App\Models\Radio $radio): RedirectResponse
    {
        $radio->update(['archivee_le' => null]);
        $this->ok('Radio remise dans les radios reçues.');

        return back();
    }

    public function radioArchiver(\App\Models\Radio $radio): RedirectResponse
    {
        $radio->update(['archivee_le' => now()]);
        $this->ok('Radio archivée (visible dans l\'historique).');

        return back();
    }

    public function radioVue(Request $request, \App\Models\Radio $radio): RedirectResponse
    {
        $radio->update(['vue_labo_le' => now(), 'vue_labo_par' => $request->user()->id]);

        return back();
    }

    /** Demande écrite librement (docteur, finance, réception) : elle arrive chez la cheffe du labo. */
    public function store(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'demande' => ['required', 'string', 'max:3000'],
            'date_pose' => ['nullable', 'date'],
        ], [], ['demande' => 'demande au labo']);
        $demande = trim($data['demande']);
        // Relie la demande à l'acte prévu dont le nom est écrit (pour la date de pose et le RDV de pose)
        $acte = $dossier->actes()->whereIn('statut', ['a_faire', 'en_cours'])->get()
            ->sortByDesc(fn ($a) => mb_strlen($a->libelle))
            ->first(fn ($a) => str_contains(mb_strtolower($demande), mb_strtolower($a->libelle)));
        $data = [
            'acte_id' => $acte?->id,
            'travail' => mb_substr(trim(strtok($demande, "\n")), 0, 255),
            'instructions' => $demande,
            'date_pose' => $data['date_pose'] ?? null,
        ];

        if (empty($data['date_pose'])) {
            $data['date_pose'] = RendezVous::where('patient_id', $dossier->patient_id)->where('debut', '>', now())
                ->whereIn('statut', [StatutRdv::Planifie->value])
                ->when($data['acte_id'] ?? null, fn ($q, $a) => $q->where('acte_id', $a))
                ->orderBy('debut')->value('debut');
            $data['date_pose'] = $data['date_pose'] ? substr((string) $data['date_pose'], 0, 10) : null;
        }

        $dossier->commandesLabo()->create($data + ['envoye_par' => $request->user()->id, 'statut' => StatutLabo::AFaire]);
        $this->ok('Demande envoyée : la cheffe du labo va l’accepter et la confier.');

        return back();
    }

    public function update(Request $request, CommandeLabo $commande): RedirectResponse
    {
        $this->autoriser($request, $commande);
        $data = $request->validate([
            'statut' => ['sometimes', Rule::enum(StatutLabo::class)],
            'technicien_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('role', \App\Enums\Role::Labo->value)],
            'date_prete' => ['sometimes', 'nullable', 'date'],
        ]);
        // Confier à quelqu'un d'autre / changer la date : seulement la cheffe du labo
        abort_if((array_key_exists('technicien_id', $data) || array_key_exists('date_prete', $data)) && ! $request->user()->estChefLabo(), 403);
        // Attribuer le travail à une personne du labo / changer la date
        if (! array_key_exists('statut', $data)) {
            $commande->update($data);
            $this->ok($commande->technicien_id ? "Travail confié à {$commande->technicien->name}." : 'Travail non attribué.');

            return back();
        }
        $commande->update($data + ($data['statut'] === StatutLabo::Pret->value ? ['pret_le' => now()] : []));

        // Travail prêt : si la pose n'a pas encore de rendez-vous, la réception le voit dans « RDV à fixer »
        if ($commande->statut === StatutLabo::Pret) {
            $dossier = $commande->dossier;
            $aRdv = $commande->acte_id && \App\Models\RendezVous::where('acte_id', $commande->acte_id)->where('debut', '>=', now())
                ->whereIn('statut', ['planifie', 'arrive'])->exists();
            $dejaDemande = \App\Models\RdvAFixer::where('patient_id', $dossier->patient_id)->where('statut', 'en_attente')
                ->where('mission', 'like', 'Pose%')->exists();
            if (! $aRdv && ! $dejaDemande) {
                \App\Models\RdvAFixer::create([
                    'patient_id' => $dossier->patient_id,
                    'docteur_id' => \App\Models\User::find($commande->envoye_par)?->role === \App\Enums\Role::Docteur ? $commande->envoye_par : null,
                    'acte_id' => $commande->acte_id,
                    'mission' => 'Pose '.$commande->travail.($commande->dents ? ' · '.$commande->dents : ''),
                    'notes' => 'Travail prêt au labo : appeler le patient pour la pose.',
                    'demande_par' => $request->user()->id,
                ]);
            }
        }

        // Travail livré / posé : les radios du patient sont archivées (jamais supprimées).
        // Posé = terminé : la commande va aussi dans l'archive. Sinon le labo archive lui-même.
        if (in_array($commande->statut, [StatutLabo::Livre, StatutLabo::Pose], true)) {
            \App\Models\Radio::where('dossier_id', $commande->dossier_id)->whereNull('archivee_le')->update(['archivee_le' => now()]);
            if ($commande->statut === StatutLabo::Pose) {
                $commande->update(['archivee_le' => now()]);
            }
            $this->ok("Commande {$commande->statut->label()}.".($commande->statut === StatutLabo::Pose ? ' Elle passe dans l\'archive.' : ''));
        } else {
            $this->ok('Commande : '.$commande->statut->label());
        }

        return back();
    }
}
