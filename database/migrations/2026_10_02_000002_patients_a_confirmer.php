<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tous les patients dont la fiche n'est pas complète passent par l'étape « à confirmer » à leur prochaine arrivée
        DB::table('patients')->where('statut_fiche', 'validee')->whereNull('date_naissance')->update(['fiche_a_completer' => true]);
    }

    public function down(): void {}
};
