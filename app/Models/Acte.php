<?php

namespace App\Models;

use App\Enums\StatutActe;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acte extends Model
{
    use Journalise, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['statut' => StatutActe::class, 'prix_convenu' => 'float', 'fait_le' => 'datetime'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function prevuPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prevu_par')->withTrashed();
    }

    public function realisePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realise_par')->withTrashed();
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }
}
