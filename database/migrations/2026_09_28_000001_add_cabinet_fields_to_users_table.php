<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('secretaire')->index()->after('email');
            $table->string('telephone', 20)->nullable()->after('role');
            $table->boolean('actif')->default(true)->after('telephone');
            $table->string('specialite', 100)->nullable()->after('actif');   // consultation, prothèse, implants...
            $table->string('couleur', 7)->nullable()->after('specialite');   // couleur dans l'agenda
            $table->string('salle', 20)->nullable()->after('couleur');
            $table->string('etat', 10)->default('absent')->after('salle');   // pret, occupe, pause, absent
            $table->timestamp('etat_depuis')->nullable()->after('etat');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'telephone', 'actif', 'specialite', 'couleur', 'salle', 'etat', 'etat_depuis', 'deleted_at']);
        });
    }
};
