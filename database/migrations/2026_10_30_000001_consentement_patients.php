<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loi 09-08 / CNDP : consentement du patient au traitement de ses données (tablette ou fiche papier)
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('consentement_le')->nullable()->after('fiche_a_completer');
            $table->string('consentement_mode', 10)->nullable()->after('consentement_le');
            $table->boolean('consentement_sms')->default(false)->after('consentement_mode');
            $table->boolean('consentement_photos')->default(false)->after('consentement_sms');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['consentement_le', 'consentement_mode', 'consentement_sms', 'consentement_photos']);
        });
    }
};
