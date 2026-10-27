<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lien secret du QR code de la carte patient (page « Mon dossier » toujours à jour)
        Schema::table('patients', function (Blueprint $table) {
            $table->string('carte_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['carte_token']);
            $table->dropColumn('carte_token');
        });
    }
};
