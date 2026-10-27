<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeRadio extends Model
{
    use Journalise;

    protected $table = 'demandes_radio';

    protected $guarded = ['id'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function demandePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par')->withTrashed();
    }

    /** Première consultation : la panoramique est demandée automatiquement si le patient n'en a pas. */
    public static function panoramiqueSiBesoin(Dossier $dossier, ?int $par, string $mission): ?self
    {
        if (! str_contains(mb_strtolower($mission), 'consult')) {
            return null;
        }
        if ($dossier->radios()->where('type', 'panoramique')->exists()
            || static::where('dossier_id', $dossier->id)->where('type', 'panoramique')->where('statut', 'en_attente')->exists()) {
            return null;
        }

        return static::create(['dossier_id' => $dossier->id, 'type' => 'panoramique', 'demande_par' => $par, 'notes' => 'Première consultation']);
    }
}
