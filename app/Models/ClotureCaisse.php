<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClotureCaisse extends Model
{
    use Journalise;

    protected $table = 'clotures_caisse';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'totaux' => 'array', 'total' => 'float', 'especes_comptees' => 'float', 'ecart' => 'float'];

    public function cloturePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par')->withTrashed();
    }
}
