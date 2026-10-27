<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plan de traitement proposé après une consultation, en attente de la validation du chef. */
class PlanAValider extends Model
{
    use Journalise;

    protected $table = 'plans_a_valider';

    protected $guarded = ['id'];

    protected $casts = ['valide_le' => 'datetime'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class)->withTrashed();
    }

    public function proposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propose_par')->withTrashed();
    }

    public function docteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_id')->withTrashed();
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par')->withTrashed();
    }

    /** @return string[] */
    public function lignes(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->actes))));
    }
}
