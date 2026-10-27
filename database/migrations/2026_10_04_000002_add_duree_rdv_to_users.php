<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Durée habituelle d'un rendez-vous chez ce docteur (minutes)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->unsignedSmallInteger('duree_rdv')->nullable()->after('salle'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('duree_rdv'));
    }
};
