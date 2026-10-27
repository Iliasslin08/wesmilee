<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Commentaire laissé par le patient depuis sa carte (QR) ou « Mon espace ». Seul le chef les voit. */
class Avis extends Model
{
    protected $table = 'avis';

    protected $guarded = ['id'];

    protected $casts = ['lu_le' => 'datetime'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }
}
