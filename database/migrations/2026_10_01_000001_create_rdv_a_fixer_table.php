<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prochain rendez-vous demandé par un docteur : la réception fixe la date avec le patient.
        Schema::create('rdv_a_fixer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('docteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acte_id')->nullable()->constrained('actes')->nullOnDelete();
            $table->string('mission');
            $table->text('notes')->nullable();
            $table->foreignId('demande_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('en_attente'); // en_attente, fixe, annule
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rdv_a_fixer');
    }
};
