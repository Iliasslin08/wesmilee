<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Labo : les demandes sont écrites librement (docteur, finance, réception) ; la cheffe du labo
 * (Sofia MOSTAFI) les accepte, les confie à une personne du labo avec la date où le travail doit être prêt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('chef_labo')->default(false);
        });
        Schema::table('commandes_labo', function (Blueprint $table) {
            $table->timestamp('acceptee_le')->nullable();
            $table->foreignId('acceptee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_prete')->nullable(); // date où le travail doit être prêt
        });
        // Commandes existantes : considérées comme déjà acceptées
        DB::table('commandes_labo')->update(['acceptee_le' => DB::raw('created_at')]);
        DB::table('users')->where('email', 'sofia.mostafi@wesmile.ma')->update(['chef_labo' => true]);
    }

    public function down(): void
    {
        Schema::table('commandes_labo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acceptee_par');
            $table->dropColumn(['acceptee_le', 'date_prete']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('chef_labo'));
    }
};
