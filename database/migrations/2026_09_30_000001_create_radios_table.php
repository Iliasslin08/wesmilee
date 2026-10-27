<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Radios faites par la radiologie (Ikram) : envoyées automatiquement au labo avec les mesures
        Schema::create('radios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('dents', 100)->nullable();
            $table->text('mesures')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('faite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vue_labo_le')->nullable();
            $table->foreignId('vue_labo_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('radio_id')->nullable()->after('patient_id')->constrained('radios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $t) => $t->dropConstrainedForeignId('radio_id'));
        Schema::dropIfExists('radios');
    }
};
