<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mon espace : mot de passe donné par l'accueil + mutuelle du patient
        Schema::table('patients', function (Blueprint $table) {
            $table->string('mot_de_passe_espace')->nullable()->after('consentement_photos');
            $table->string('mutuelle', 30)->nullable()->after('mot_de_passe_espace');
            $table->string('mutuelle_numero', 40)->nullable()->after('mutuelle');
        });

        // Messages envoyés au patient (Mon espace + SMS s'il a accepté)
        Schema::create('messages_patient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('info'); // controle, implant, labo, paiement, info
            $table->string('titre');
            $table->text('texte');
            $table->string('cle', 100)->nullable()->index(); // évite d'envoyer deux fois le même rappel automatique
            $table->boolean('sms_envoye')->default(false);
            $table->timestamp('lu_le')->nullable();
            $table->foreignId('envoye_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Feuilles de soins remises au patient pour sa mutuelle
        Schema::create('feuilles_soins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->string('organisme', 30);
            $table->decimal('montant', 10, 2)->default(0);
            $table->date('remise_le');
            $table->string('statut', 15)->default('remise'); // remise, remboursee, refusee
            $table->string('note')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feuilles_soins');
        Schema::dropIfExists('messages_patient');
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['mot_de_passe_espace', 'mutuelle', 'mutuelle_numero']);
        });
    }
};
