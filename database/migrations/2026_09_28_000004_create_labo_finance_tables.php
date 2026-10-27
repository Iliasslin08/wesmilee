<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes_labo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acte_id')->nullable()->constrained('actes')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('commandes_labo')->nullOnDelete(); // travail refait
            $table->boolean('garantie')->default(false);
            $table->string('travail');
            $table->string('dents', 100)->nullable();
            $table->string('materiau', 50)->nullable();
            $table->string('teinte', 20)->nullable();
            $table->text('instructions')->nullable();
            $table->date('date_pose')->nullable();   // date du rendez-vous de pose
            $table->string('statut', 15)->default('a_faire');
            $table->foreignId('envoye_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pret_le')->nullable();
            $table->timestamps();
            $table->index(['statut', 'date_pose']);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu', 20)->unique();
            $table->foreignId('dossier_id')->constrained()->restrictOnDelete();
            $table->decimal('montant', 10, 2);
            $table->string('methode', 20); // especes, carte, cheque, virement
            $table->date('date');
            $table->string('facture_a')->nullable();
            $table->decimal('reste_apres', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('encaisse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('sujet_type');
            $table->unsignedBigInteger('sujet_id');
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sujet_type', 'sujet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('commandes_labo');
    }
};
