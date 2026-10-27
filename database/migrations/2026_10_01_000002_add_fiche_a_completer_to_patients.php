<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Patient créé au téléphone (nom, prénom, tél.) : dossier « à confirmer » tant que la fiche n'est pas remplie.
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('fiche_a_completer')->default(false)->after('statut_fiche');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('fiche_a_completer');
        });
    }
};
