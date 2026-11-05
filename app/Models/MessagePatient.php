<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Message affiché dans « Mon espace » (et envoyé par SMS si le patient l'a accepté). */
class MessagePatient extends Model
{
    protected $table = 'messages_patient';

    protected $guarded = ['id'];

    protected $casts = ['lu_le' => 'datetime', 'sms_envoye' => 'boolean'];

    public const TYPES = ['controle' => 'Contrôle', 'implant' => 'Implant', 'labo' => 'Laboratoire', 'paiement' => 'Paiement', 'info' => 'Information'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function envoyePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envoye_par')->withTrashed();
    }
}
