<?php

use App\Models\CatalogueActe;
use Illuminate\Database\Migrations\Migration;

// Tarifs du cabinet (feuille papier) : All on 4/6, implants, greffes, couronnes zircone par lot.
// Les prix restent modifiables : Catalogue des actes (direction) et devis (finance). Les docteurs ne voient pas les prix.
return new class extends Migration
{
    public function up(): void
    {
        $chir = 'Implants, chirurgie';
        $proth = 'Prothèse, esthétique';
        foreach ([
            // libellé, spécialité, prix (DH), durée (min), labo
            ['All on 4 du haut', $chir, 25000, 180, true],
            ['All on 4 du bas', $chir, 25000, 180, true],
            ['All on 6 du haut', $chir, 35000, 180, true],
            ['All on 6 du bas', $chir, 35000, 180, true],
            ['Bone titane du haut', $chir, 5000, 60, false],
            ['Bone titane du bas', $chir, 5000, 60, false],
            ['Greffe d’os du haut', $chir, 5000, 60, false],
            ['Greffe d’os du bas', $chir, 5000, 60, false],
            ['Implant', $chir, 7000, 90, false],
            ['Implant offert', $chir, 0, 90, false],
            ['Couronne zircone', $proth, 2000, 45, true],
            ['Couronne zircone (à partir de 10, prix par dent)', $proth, 1400, 45, true],
            ['Couronne zircone (à partir de 20, prix par dent)', $proth, 1300, 45, true],
            ['10 couronnes zircone', $proth, 14000, 60, true],
            ['20 couronnes zircone', $proth, 26000, 90, true],
        ] as [$libelle, $spec, $prix, $duree, $labo]) {
            CatalogueActe::updateOrCreate(['libelle' => $libelle], [
                'specialite' => $spec, 'prix_defaut' => $prix, 'duree_minutes' => $duree, 'necessite_labo' => $labo, 'actif' => true,
            ]);
        }
    }

    public function down(): void {}
};
