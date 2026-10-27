<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtatDent extends Model
{
    protected $guarded = ['id'];

    public const ETATS = ['saine', 'carie', 'a_surveiller', 'traitee', 'couronne', 'implant', 'absente'];
}
