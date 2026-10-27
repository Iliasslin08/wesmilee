<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les avis sont seulement des commentaires (plus d'étoiles). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('avis', 'note')) {
            Schema::table('avis', fn (Blueprint $t) => $t->dropColumn('note'));
        }
    }

    public function down(): void
    {
    }
};
