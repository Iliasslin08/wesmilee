<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demande de radio envoyée à la radiologie (panoramique de la 1re consultation, ou demande d'un docteur)
        Schema::create('demandes_radio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->default('panoramique');
            $table->text('notes')->nullable();
            $table->foreignId('demande_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('en_attente'); // en_attente, faite
            $table->foreignId('radio_id')->nullable()->constrained('radios')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_radio');
    }
};
