<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Radio archivée (jamais supprimée) quand le travail du labo est livré
        Schema::table('radios', function (Blueprint $table) {
            $table->timestamp('archivee_le')->nullable()->after('vue_labo_par');
        });
    }

    public function down(): void
    {
        Schema::table('radios', fn (Blueprint $t) => $t->dropColumn('archivee_le'));
    }
};
