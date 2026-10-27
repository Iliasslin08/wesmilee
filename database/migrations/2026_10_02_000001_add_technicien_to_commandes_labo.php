<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personne du labo chargée du travail (selon sa mission : prothésiste, céramiste, CAD/CAM)
        Schema::table('commandes_labo', function (Blueprint $table) {
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commandes_labo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technicien_id');
        });
    }
};
