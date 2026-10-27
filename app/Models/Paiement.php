<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    use Journalise;

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'montant' => 'float', 'reste_apres' => 'float'];

    protected static function booted(): void
    {
        static::creating(function (Paiement $p) {
            if (! $p->numero_recu) {
                $dernier = (int) static::query()->max('numero_recu');
                $suivant = max($dernier + 1, config('cabinet.premier_numero_recu'));
                $p->numero_recu = str_pad((string) $suivant, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function encaissePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par')->withTrashed();
    }
}
