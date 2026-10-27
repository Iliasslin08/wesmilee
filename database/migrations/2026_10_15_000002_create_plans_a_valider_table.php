<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plan proposé par le docteur de consultation : le chef valide les actes, le docteur et la date. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_a_valider', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('propose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('docteur_id')->nullable()->constrained('users')->nullOnDelete(); // docteur proposé
            $table->text('actes');                 // une ligne par acte : « Libellé · dents »
            $table->text('notes')->nullable();     // remarques écrites du docteur de consultation
            $table->string('statut', 12)->default('en_attente'); // en_attente, valide, refuse
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->text('reponse')->nullable();   // message du chef (refus)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans_a_valider');
    }
};
