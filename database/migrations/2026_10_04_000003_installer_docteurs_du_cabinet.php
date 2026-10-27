<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

// Remplace les anciens docteurs par ceux du cabinet (config/docteurs.php), mot de passe « password »
return new class extends Migration
{
    public function up(): void
    {
        // En ligne (production) : on crée les docteurs à la main avec « php artisan cabinet:docteurs » (vrais horaires, vrais mots de passe)
        if (app()->runningUnitTests() || app()->isProduction()) {
            return;
        }
        Artisan::call('cabinet:docteurs', ['--reset' => true]);
        echo Artisan::output();
    }

    public function down(): void {}
};
