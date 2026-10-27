<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

// Ajoute Dr BASKALI Oussama (chirurgie, implantologie, endodontie, prothèse fixée) (config/docteurs.php) ; phase de test : docteurs 24h/24, 7j/7
return new class extends Migration
{
    public function up(): void
    {
        // En ligne (production) : on crée les docteurs à la main avec « php artisan cabinet:docteurs » (vrais horaires, vrais mots de passe)
        if (app()->runningUnitTests() || app()->isProduction()) {
            return;
        }
        Artisan::call('cabinet:docteurs', ['--test-24h' => true]);
        echo Artisan::output();
    }

    public function down(): void {}
};
