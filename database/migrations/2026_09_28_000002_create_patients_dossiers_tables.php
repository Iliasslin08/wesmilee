<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->date('date_naissance')->nullable();
            $table->string('sexe', 1)->nullable();
            $table->string('cin', 20)->nullable()->index();
            $table->string('profession', 100)->nullable();
            $table->string('telephone', 20)->nullable()->index();
            $table->json('anciens_telephones')->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->json('antecedents')->nullable();          // ["diabete","hypertension",...]
            $table->text('antecedents_autres')->nullable();
            $table->text('allergies')->nullable();
            $table->text('traitements')->nullable();
            $table->string('statut_fiche', 12)->default('a_valider'); // a_valider, validee
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['nom', 'prenom']);
        });

        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('ancien_numero_papier', 20)->nullable()->index();
            $table->foreignId('patient_id')->unique()->constrained()->restrictOnDelete(); // un dossier à vie
            $table->foreignId('docteur_consultation_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('en_traitement'); // en_traitement, en_suivi
            $table->text('diagnostic')->nullable();
            $table->text('notes')->nullable();
            $table->date('prochain_controle')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('catalogue_actes', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('specialite', 100)->nullable();
            $table->decimal('prix_defaut', 10, 2)->nullable();
            $table->unsignedSmallInteger('duree_minutes')->default(30);
            $table->boolean('necessite_labo')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_actes');
        Schema::dropIfExists('dossiers');
        Schema::dropIfExists('patients');
    }
};
