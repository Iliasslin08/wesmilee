<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Informations du cabinet modifiables depuis l'espace du chef (remplacent config/cabinet.php). */
class Parametre extends Model
{
    public const CHAMPS = [
        'nom' => 'Nom du cabinet',
        'adresse' => 'Adresse',
        'ville' => 'Ville',
        'telephone' => 'Téléphone',
        'site' => 'Site internet',
        'slogan' => 'Slogan (ex. Urgence dentaire 24h/24)',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'chef_plateau' => 'Chef de plateau',
    ];

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** Charge les paramètres enregistrés dans config('cabinet.*'). */
    public static function charger(): void
    {
        try {
            if (! Schema::hasTable('parametres')) {
                return;
            }
            foreach (static::pluck('valeur', 'cle') as $cle => $valeur) {
                if (filled($valeur)) {
                    config(["cabinet.$cle" => $valeur]);
                }
            }
        } catch (\Throwable) {
            // base pas encore prête (installation) : on garde config/cabinet.php
        }
    }
}
