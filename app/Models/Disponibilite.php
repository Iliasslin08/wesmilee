<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disponibilite extends Model
{
    protected $guarded = ['id'];

    public function docteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_id')->withTrashed();
    }
}
