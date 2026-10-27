<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seance extends Model
{
    use Journalise;

    protected $guarded = ['id'];

    protected $casts = ['debut' => 'datetime', 'fin' => 'datetime'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function docteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_id')->withTrashed();
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }

    public function actes(): HasMany
    {
        return $this->hasMany(Acte::class);
    }
}
