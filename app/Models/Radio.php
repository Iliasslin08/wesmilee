<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Radio extends Model
{
    use Journalise;

    public const TYPES = [
        'panoramique' => 'Panoramique',
        'retro_alveolaire' => 'Rétro-alvéolaire',
        'cone_beam' => 'Cone beam (3D)',
        'teleradio' => 'Téléradiographie',
        'scan_intra_oral' => 'Empreinte / scan intra-oral',
        'autre' => 'Autre',
    ];

    protected $guarded = ['id'];

    protected $casts = ['vue_labo_le' => 'datetime', 'archivee_le' => 'datetime'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function fairePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faite_par')->withTrashed();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
