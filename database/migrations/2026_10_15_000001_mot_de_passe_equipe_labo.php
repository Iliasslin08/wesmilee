<?php

use App\Console\Commands\CreerEquipeLabo;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

// Phase de test : l'équipe du labo se connecte avec « password » comme les autres comptes (jamais en production)
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || app()->isProduction()) {
            return;
        }
        foreach (CreerEquipeLabo::EQUIPE as [$nom, $prenom]) {
            $email = Str::slug($prenom.'.'.$nom, '.').'@wesmile.ma';
            if ($u = User::where('email', $email)->first()) {
                $u->update(['password' => 'password']);
                echo "Labo : $email / password\n";
            }
        }
    }

    public function down(): void {}
};
