<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Infos des docteurs (fichier Excel : consultation, assistante, tâches)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('fait_consultation')->default(false)->after('specialite');
            $table->string('assistante')->nullable()->after('fait_consultation');
            $table->text('taches_principales')->nullable()->after('assistante');
            $table->text('taches_secondaires')->nullable()->after('taches_principales');
            $table->text('autres_taches')->nullable()->after('taches_secondaires');
        });

        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50)->unique();
            $table->string('equipement')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Informations du cabinet modifiables par le chef (nom, adresse, téléphone…)
        Schema::create('parametres', function (Blueprint $table) {
            $table->string('cle', 60)->primary();
            $table->text('valeur')->nullable();
            $table->timestamps();
        });

        Schema::create('clotures_caisse', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('totaux');
            $table->decimal('total', 10, 2);
            $table->unsignedInteger('nb_paiements')->default(0);
            $table->decimal('especes_comptees', 10, 2)->nullable();
            $table->decimal('ecart', 10, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Fusion de doublons : le dossier fusionné pointe vers celui qu'on garde
        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreignId('fusionne_dans_id')->nullable()->after('cree_par')->constrained('dossiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', fn (Blueprint $t) => $t->dropConstrainedForeignId('fusionne_dans_id'));
        Schema::dropIfExists('clotures_caisse');
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('salles');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['fait_consultation', 'assistante', 'taches_principales', 'taches_secondaires', 'autres_taches']));
    }
};
