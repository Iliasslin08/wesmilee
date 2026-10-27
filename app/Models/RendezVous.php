<?php

namespace App\Models;

use App\Enums\StatutRdv;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RendezVous extends Model
{
    use Journalise;

    protected $table = 'rendez_vous';

    protected $guarded = ['id'];

    protected $casts = [
        'debut' => 'datetime', 'fin' => 'datetime', 'arrive_le' => 'datetime', 'appele_le' => 'datetime',
        'statut' => StatutRdv::class, 'sans_rdv' => 'boolean', 'urgence' => 'boolean', 'en_ligne' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function docteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_id')->withTrashed();
    }

    public function acte(): BelongsTo
    {
        return $this->belongsTo(Acte::class);
    }

    public function seance(): HasOne
    {
        return $this->hasOne(Seance::class);
    }

    public function scopeDuJour(Builder $q, $date = null): Builder
    {
        $d = $date ? \Illuminate\Support\Carbon::parse($date) : now();

        return $q->whereBetween('debut', [$d->copy()->startOfDay(), $d->copy()->endOfDay()]);
    }

    public function scopeActifs(Builder $q): Builder
    {
        return $q->whereIn('statut', StatutRdv::actifs());
    }
}
