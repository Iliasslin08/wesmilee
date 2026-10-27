<?php

use App\Models\Dossier;
use App\Models\RendezVous;
use Illuminate\Database\Migrations\Migration;

// Les RDV déjà pris (aujourd'hui et à venir) sans acte : « Consultation » ou « Contrôle » dans « À faire »
return new class extends Migration
{
    public function up(): void
    {
        RendezVous::whereNull('acte_id')->where('debut', '>=', now()->startOfDay())
            ->whereIn('statut', ['planifie', 'arrive', 'en_cours'])->orderBy('debut')->get()
            ->each(function (RendezVous $rdv) {
                Dossier::where('patient_id', $rdv->patient_id)->first()?->acteDeVisite($rdv);
            });
    }

    public function down(): void {}
};
