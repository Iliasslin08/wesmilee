<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Feuille de soins remise au patient pour sa mutuelle (suivi du remboursement). */
class FeuilleSoins extends Model
{
    use Journalise;

    protected $table = 'feuilles_soins';

    protected $guarded = ['id'];

    protected $casts = ['remise_le' => 'date', 'montant' => 'float'];

    public const STATUTS = ['remise' => 'Remise au patient', 'remboursee' => 'Remboursée', 'refusee' => 'Refusée'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par')->withTrashed();
    }
}
