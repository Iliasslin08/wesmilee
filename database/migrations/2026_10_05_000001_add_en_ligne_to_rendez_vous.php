<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rendez-vous pris par le patient lui-même dans « Mon espace » (badge « En ligne » à la réception)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', fn (Blueprint $t) => $t->boolean('en_ligne')->default(false)->after('sans_rdv'));
    }

    public function down(): void
    {
        Schema::table('rendez_vous', fn (Blueprint $t) => $t->dropColumn('en_ligne'));
    }
};
