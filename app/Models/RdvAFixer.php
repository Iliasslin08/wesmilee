<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prochain rendez-vous demandé par un docteur, en attente d'une date (fixée par la réception). */
class RdvAFixer extends Model
{
    use Journalise;

    protected $table = 'rdv_a_fixer';

    protected $guarded = ['id'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function docteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_id')->withTrashed();
    }

    public function demandePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par')->withTrashed();
    }
}
