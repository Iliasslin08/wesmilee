<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $guarded = ['id'];

    public const TYPES = [
        'cin' => 'Carte nationale (CIN)',
        'ordonnance' => 'Ordonnance / analyses',
        'assurance' => 'Mutuelle / assurance',
        'photo_avant' => 'Photo avant',
        'photo_apres' => 'Photo après',
        'photo_carte' => 'Photo carte patient',
        'radio' => 'Radio',
        'consentement' => 'Consentement / devis signé',
        'ancien_dossier' => 'Ancien dossier papier',
        'recu' => 'Reçu papier',
        'autre' => 'Autre',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ajoutePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajoute_par')->withTrashed();
    }
}
