<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, Journalise, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['carte_token'];

    protected $casts = [
        'date_naissance' => 'date',
        'antecedents' => 'array',
        'anciens_telephones' => 'array',
        'valide_le' => 'datetime',
        'fiche_a_completer' => 'boolean',
    ];

    protected $appends = ['nom_complet', 'age', 'vient_de_loin'];

    public function getNomCompletAttribute(): string
    {
        return mb_strtoupper($this->nom).' '.$this->prenom;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_naissance?->age;
    }

    public function getVientDeLoinAttribute(): bool
    {
        return filled($this->ville)
            && mb_strtolower(trim($this->ville)) !== mb_strtolower(config('cabinet.ville'));
    }

    public function dossier(): HasOne
    {
        return $this->hasOne(Dossier::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest();
    }

    /**
     * Patients déjà enregistrés qui ressemblent à ces données (même CIN, même téléphone,
     * même nom + prénom, ou même nom + date de naissance).
     */
    public static function doublonsPossibles(array $d, ?int $ignorer = null): \Illuminate\Support\Collection
    {
        $nom = mb_strtolower(trim($d['nom'] ?? ''));
        $prenom = mb_strtolower(trim($d['prenom'] ?? ''));
        $tel = preg_replace('/\D/', '', $d['telephone'] ?? '');

        return static::where('statut_fiche', 'validee')
            ->when($ignorer, fn ($q) => $q->where('id', '!=', $ignorer))
            ->where(function ($q) use ($d, $nom, $prenom, $tel) {
                $q->whereRaw('1 = 0');
                if (filled($d['cin'] ?? null)) {
                    $q->orWhere('cin', trim($d['cin']));
                }
                if (strlen($tel) >= 8) {
                    $q->orWhere('telephone', 'like', '%'.substr($tel, -8).'%');
                }
                if ($nom && $prenom) {
                    $q->orWhere(fn ($x) => $x->whereRaw('lower(nom) = ?', [$nom])->whereRaw('lower(prenom) = ?', [$prenom]));
                }
                if ($nom && filled($d['date_naissance'] ?? null)) {
                    $q->orWhere(fn ($x) => $x->whereRaw('lower(nom) = ?', [$nom])->whereDate('date_naissance', $d['date_naissance']));
                }
            })
            ->with('dossier:id,patient_id,numero')
            ->limit(5)->get(['id', 'nom', 'prenom', 'telephone', 'cin', 'ville', 'date_naissance'])
            ->map(function (Patient $p) use ($d, $tel) {
                $raisons = [];
                if (filled($d['cin'] ?? null) && $p->cin === trim($d['cin'])) {
                    $raisons[] = 'même CIN';
                }
                if (strlen($tel) >= 8 && str_contains(preg_replace('/\D/', '', (string) $p->telephone), substr($tel, -8))) {
                    $raisons[] = 'même téléphone';
                }
                if (mb_strtolower($p->nom) === mb_strtolower(trim($d['nom'] ?? '')) && mb_strtolower($p->prenom) === mb_strtolower(trim($d['prenom'] ?? ''))) {
                    $raisons[] = 'même nom et prénom';
                }

                return $p->toArray() + ['raisons' => $raisons ?: ['même nom et date de naissance']];
            });
    }

    /** Recherche par nom, prénom, téléphone, CIN, n° de dossier ou ancien n° papier. */
    public function scopeRecherche(Builder $q, ?string $terme): Builder
    {
        if (! filled($terme)) {
            return $q;
        }
        $t = '%'.trim($terme).'%';
        $concat = $q->getConnection()->getDriverName() === 'sqlite' ? "(nom || ' ' || prenom)" : "CONCAT(nom, ' ', prenom)";

        return $q->where(function ($q) use ($t, $concat) {
            $q->where('nom', 'like', $t)->orWhere('prenom', 'like', $t)
                ->orWhere('telephone', 'like', $t)->orWhere('cin', 'like', $t)
                ->orWhereRaw($concat.' like ?', [$t])
                ->orWhereHas('dossier', fn ($d) => $d->where('numero', 'like', $t)->orWhere('ancien_numero_papier', 'like', $t));
        });
    }

    /** Lien secret de la carte patient (créé à la première impression). */
    public function carteToken(bool $nouveau = false): string
    {
        if ($nouveau || ! $this->carte_token) {
            $this->forceFill(['carte_token' => \Illuminate\Support\Str::random(40)])->saveQuietly();
        }

        return $this->carte_token;
    }

    /** Lien du QR code (adresse joignable depuis le téléphone du patient, voir CARTE_URL dans .env). */
    public function lienCarte(): string
    {
        return self::baseCarte().'/c/'.$this->carteToken();
    }

    /**
     * Adresse de base du QR code. Priorité : CARTE_URL (.env). Si elle manque ou pointe vers
     * localhost / 127.0.0.1 (introuvable depuis un téléphone), on prend automatiquement
     * l'adresse IP du PC sur le Wi-Fi.
     */
    public static function baseCarte(): string
    {
        $base = rtrim((string) (config('cabinet.carte_url') ?: url('/')), '/');
        if (! preg_match('#//(localhost|127\.0\.0\.1)(:|/|$)#', $base)) {
            return $base;
        }
        $ip = gethostbyname(gethostname());
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP) && ! str_starts_with($ip, '127.')) {
            $port = parse_url($base, PHP_URL_PORT) ?: request()->getPort();

            return 'http://'.$ip.($port && $port != 80 ? ':'.$port : '');
        }

        return $base;
    }

    /** Photo de la carte patient (la plus récente). */
    public function photoCarte(): ?Document
    {
        return $this->documents()->where('type', 'photo_carte')->latest('id')->first();
    }
}
