<?php

namespace App\Models;

use App\Enums\StatutActe;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dossier extends Model
{
    use Journalise, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['prochain_controle' => 'date'];

    protected static function booted(): void
    {
        static::creating(function (Dossier $d) {
            if (! $d->numero) {
                // Suite du plus grand n° (y compris les n° repris de l'ancien logiciel)
                $cast = static::query()->getConnection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
                $plusGrand = (int) static::withTrashed()->selectRaw("max(cast(numero as $cast)) as m")->value('m');
                $d->numero = (string) max((int) config('cabinet.premier_numero_dossier'), $plusGrand + 1);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function docteurConsultation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_consultation_id')->withTrashed();
    }

    public function actes(): HasMany
    {
        return $this->hasMany(Acte::class);
    }

    public function ordonnances(): HasMany
    {
        return $this->hasMany(Ordonnance::class)->latest();
    }

    public function demandesRadio(): HasMany
    {
        return $this->hasMany(DemandeRadio::class)->latest();
    }

    public function radios(): HasMany
    {
        return $this->hasMany(Radio::class)->latest();
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class)->latest('debut');
    }

    public function etatsDents(): HasMany
    {
        return $this->hasMany(EtatDent::class);
    }

    public function commandesLabo(): HasMany
    {
        return $this->hasMany(CommandeLabo::class)->latest();
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class)->latest('id');
    }

    public function totalConvenu(): float
    {
        return (float) $this->actes()->where('statut', '!=', StatutActe::Annule->value)->sum('prix_convenu');
    }

    public function totalRecu(): float
    {
        return (float) $this->paiements()->sum('montant');
    }

    public function reste(): float
    {
        return round($this->totalConvenu() - $this->totalRecu(), 2);
    }

    /** Alertes affichées à l'ouverture du dossier. */
    public function alertes(): array
    {
        $a = [];
        $p = $this->patient;
        if (filled($p->allergies)) {
            $a[] = ['type' => 'danger', 'texte' => 'Allergie : '.$p->allergies];
        }
        if (! $p->documents()->where('type', 'cin')->exists()) {
            $a[] = ['type' => 'warning', 'texte' => 'Carte nationale non scannée'];
        }
        if (($r = $this->reste()) > 0) {
            $a[] = ['type' => 'warning', 'texte' => 'Reste à payer : '.number_format($r, 0, ',', ' ').' DH'];
        }
        $nonPlanifies = $this->actes()->where('statut', StatutActe::AFaire->value)
            ->whereDoesntHave('rendezVous', fn ($q) => $q->where('debut', '>=', now()->startOfDay()))->count();
        if ($nonPlanifies) {
            $a[] = ['type' => 'info', 'texte' => $nonPlanifies.' acte(s) « À faire » sans rendez-vous'];
        }
        $derniere = $this->seances()->first();
        if ($derniere && $derniere->debut->lt(now()->subMonths(6))) {
            $a[] = ['type' => 'info', 'texte' => 'Dernière visite : '.$derniere->debut->diffForHumans()];
        }

        return $a;
    }

    /**
     * Ce que le docteur doit faire à cette visite, dans « À faire » :
     * 1re visite → « Consultation + plan de traitement » ; patient revenu sans rien de prévu → « Contrôle ».
     * Rien si le RDV vise déjà un acte ou si des actes sont déjà à faire / en cours.
     */
    public function acteDeVisite(RendezVous $rdv, ?int $par = null): ?Acte
    {
        if ($rdv->acte_id) {
            return null;
        }
        $actes = $this->actes()->where('statut', '!=', StatutActe::Annule->value)->get();
        if ($actes->whereIn('statut', [StatutActe::AFaire, StatutActe::EnCours])->isNotEmpty()) {
            return null;
        }
        $premiere = $actes->isEmpty() && ! $this->seances()->whereNotNull('fin')->exists();
        $cat = $premiere ? CatalogueActe::where('libelle', 'like', 'Consultation%')->orderBy('id')->first()
            : CatalogueActe::where('libelle', 'like', 'Contr%le%')->orderBy('id')->first();
        $acte = $this->actes()->create([
            'catalogue_acte_id' => $cat?->id,
            'libelle' => $cat?->libelle ?? ($premiere ? 'Consultation + plan de traitement' : 'Contrôle'),
            'specialite' => $cat?->specialite,
            'prix_convenu' => $cat?->prix_defaut ?? 0,
            'notes' => $rdv->mission ? 'Motif : '.$rdv->mission : null,
            'statut' => StatutActe::AFaire,
            'prevu_par' => $par ?? $rdv->cree_par,
        ]);
        $rdv->update(['acte_id' => $acte->id]);

        return $acte;
    }

    /**
     * Travail demandé par un docteur pour une prochaine séance (souvent chez un autre docteur) :
     * il est toujours enregistré dans « À faire ». Acte du plan choisi → on le garde ; sinon on le crée
     * (« Implant · 36 » → acte « Implant », dent 36, prix du catalogue si l'acte y est).
     */
    /** Acte écrit « fait » par le docteur (texte libre « Libellé · dents ») : reprend l'acte prévu s'il existe. */
    public function acteFait(string $texte, ?int $par, ?int $seanceId = null): Acte
    {
        $acte = $this->acteDemande($texte, null, $par);
        $acte->update(['statut' => StatutActe::Fait, 'realise_par' => $par, 'fait_le' => now(), 'seance_id' => $seanceId ?? $acte->seance_id]);

        return $acte;
    }

    public function acteDemande(string $mission, ?int $acteId, ?int $par = null): Acte
    {
        if ($acteId && ($acte = $this->actes()->whereKey($acteId)->first())) {
            if ($acte->statut === StatutActe::Fait || $acte->statut === StatutActe::Annule) {
                $acte->update(['statut' => StatutActe::AFaire]);
            }

            return $acte;
        }
        // Même texte qu'un acte déjà prévu (« Libellé · dents », dents écrites en lettres comprises) : on le reprend
        $texte = mb_strtolower(preg_replace('/\s+/u', ' ', trim($mission)));
        $memeTexte = $this->actes()->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])->get()
            ->first(fn ($a) => mb_strtolower(preg_replace('/\s+/u', ' ', $a->libelle.($a->dents ? ' · '.$a->dents : ''))) === $texte);
        if ($memeTexte) {
            return $memeTexte;
        }
        $libelle = trim($mission);
        $dents = null;
        // « Libellé · dents » : dents en chiffres (16, 26) ou en lettres (maxillaire, mandibulaire…)
        if (preg_match('/^(.+)\s+·\s+([^·]+)$/u', $libelle, $m)) {
            [$libelle, $dents] = [trim($m[1]), trim($m[2])];
        }
        $deja = $this->actes()->whereIn('statut', [StatutActe::AFaire->value, StatutActe::EnCours->value])
            ->where('libelle', $libelle)->when($dents, fn ($q) => $q->where('dents', $dents))->first();
        if ($deja) {
            return $deja;
        }
        $cat = CatalogueActe::where('libelle', $libelle)->first() ?? CatalogueActe::where('libelle', 'like', $libelle.'%')->orderBy('id')->first();

        $acte = $this->actes()->create([
            'catalogue_acte_id' => $cat?->id,
            'libelle' => $libelle,
            'dents' => $dents,
            'specialite' => $cat?->specialite,
            'prix_convenu' => $cat?->prix_defaut ?? 0,
            'statut' => StatutActe::AFaire,
            'prevu_par' => $par,
        ]);
        if ($this->statut === 'en_suivi') {
            $this->update(['statut' => 'en_traitement']);
        }

        return $acte;
    }
}
