<?php

namespace App\Models;

use App\Enums\StatutLabo;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeLabo extends Model
{
    use Journalise;

    protected $table = 'commandes_labo';

    protected $guarded = ['id'];

    protected $casts = ['date_prete' => 'date:Y-m-d', 'acceptee_le' => 'datetime', 'date_pose' => 'date', 'pret_le' => 'datetime', 'archivee_le' => 'datetime', 'garantie' => 'boolean', 'statut' => StatutLabo::class];

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technicien_id')->withTrashed();
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function acte(): BelongsTo
    {
        return $this->belongsTo(Acte::class);
    }

    public function acceptePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acceptee_par')->withTrashed();
    }

    public function envoyePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envoye_par')->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** urgent, bientot, normal, en_attente (sans RDV de pose), termine */
    public function priorite(): string
    {
        if (in_array($this->statut, [StatutLabo::Pret, StatutLabo::Livre, StatutLabo::Pose], true)) {
            return 'termine';
        }
        // La date « à rendre » fixée par le chef du labo passe avant la date de pose
        $echeance = $this->date_prete ?? $this->date_pose;
        if (! $echeance) {
            return 'en_attente';
        }
        $jours = now()->startOfDay()->diffInDays($echeance, false);
        if ($jours <= 2 || ($jours <= 4 && $this->dossier?->patient?->vient_de_loin)) {
            return 'urgent';
        }

        return $jours <= 7 ? 'bientot' : 'normal';
    }
}
