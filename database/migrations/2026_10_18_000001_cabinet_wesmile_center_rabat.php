<?php

use App\Models\Parametre;
use Illuminate\Database\Migrations\Migration;

// Informations du centre : Wesmile Center, Rabat (Agdal). Modifiables ensuite dans « Cabinet & salles ».
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'nom' => 'Wesmile Center',
            'adresse' => 'Résidence Narjiss, 68 av. Fal Ould Oumeir, Agdal',
            'ville' => 'Rabat',
            'telephone' => '05 37 73 73 77',
            'site' => 'www.wesmilegrp.com',
            'slogan' => 'Urgence dentaire 24h/24',
        ] as $cle => $valeur) {
            Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
        }
    }

    public function down(): void {}
};
