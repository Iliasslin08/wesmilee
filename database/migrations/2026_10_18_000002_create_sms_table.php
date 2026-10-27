<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rappels SMS : date d'envoi sur le RDV + journal de tous les SMS. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->timestamp('rappel_envoye_le')->nullable();
        });
        Schema::create('sms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('telephone', 30);
            $table->text('message');
            $table->string('statut', 12); // envoye, echec, test
            $table->text('erreur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms');
        Schema::table('rendez_vous', fn (Blueprint $t) => $t->dropColumn('rappel_envoye_le'));
    }
};
