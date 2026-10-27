<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal';

    protected $guarded = ['id'];

    protected $casts = ['avant' => 'array', 'apres' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
