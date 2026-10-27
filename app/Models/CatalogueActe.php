<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogueActe extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['prix_defaut' => 'float', 'actif' => 'boolean', 'necessite_labo' => 'boolean'];
}
