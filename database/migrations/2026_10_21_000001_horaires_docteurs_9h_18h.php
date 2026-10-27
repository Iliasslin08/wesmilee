<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

// Horaires de tous les docteurs : lundi–samedi, 9h–18h (remplace l'ancien 24h/24 de la phase de test)
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        User::where('role', 'docteur')->each(function (User $u) {
            $u->disponibilites()->delete();
            foreach (range(1, 6) as $jour) {
                $u->disponibilites()->create(['jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '18:00']);
            }
        });
    }

    public function down(): void {}
};
