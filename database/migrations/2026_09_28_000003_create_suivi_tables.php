<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docteur_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour_semaine'); // 1 = lundi ... 7 = dimanche
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();
        });

        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('docteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mission');                       // soin prévu
            $table->dateTime('debut');
            $table->dateTime('fin');
            $table->string('statut', 12)->default('planifie');
            $table->boolean('sans_rdv')->default(false);
            $table->boolean('urgence')->default(false);
            $table->unsignedSmallInteger('numero_appel')->nullable();
            $table->timestamp('arrive_le')->nullable();
            $table->timestamp('appele_le')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['docteur_id', 'debut']);
            $table->index(['debut', 'statut']);
        });

        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('docteur_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->dateTime('debut');
            $table->dateTime('fin')->nullable();
            $table->unsignedSmallInteger('duree_minutes')->nullable();
            $table->text('compte_rendu')->nullable();
            $table->text('suite')->nullable();              // consigne pour la suite
            $table->timestamps();
        });

        Schema::create('actes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalogue_acte_id')->nullable()->constrained('catalogue_actes')->nullOnDelete();
            $table->string('libelle');
            $table->string('dents', 100)->nullable();
            $table->string('statut', 10)->default('a_faire'); // a_faire, en_cours, fait, annule
            $table->decimal('prix_convenu', 10, 2)->default(0);
            $table->string('specialite', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prevu_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('realise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seance_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('fait_le')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['dossier_id', 'statut']);
        });

        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->foreignId('acte_id')->nullable()->after('docteur_id')->constrained('actes')->nullOnDelete();
        });

        Schema::create('etat_dents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('dent');
            $table->string('etat', 20); // carie, absente, couronne, implant, traitee, a_surveiller
            $table->timestamps();
            $table->unique(['dossier_id', 'dent']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // cin, photo_avant, photo_apres, radio, ancien_dossier, recu, autre
            $table->string('chemin');
            $table->string('nom_original')->nullable();
            $table->foreignId('ajoute_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('etat_dents');
        Schema::table('rendez_vous', fn (Blueprint $t) => $t->dropConstrainedForeignId('acte_id'));
        Schema::dropIfExists('actes');
        Schema::dropIfExists('seances');
        Schema::dropIfExists('rendez_vous');
        Schema::dropIfExists('disponibilites');
    }
};
