<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le labo range lui-même ses commandes terminées dans l'archive (et peut les ressortir).
        Schema::table('commandes_labo', function (Blueprint $table) {
            $table->timestamp('archivee_le')->nullable();
        });
        DB::table('commandes_labo')->whereIn('statut', ['livre', 'pose'])->update(['archivee_le' => now()]);
    }

    public function down(): void
    {
        Schema::table('commandes_labo', function (Blueprint $table) {
            $table->dropColumn('archivee_le');
        });
    }
};
