<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Supprime TOUS les patients et leurs données (pour repartir à zéro pendant les tests). Les comptes, salles et le catalogue restent. */
class ViderPatients extends Command
{
    protected $signature = 'cabinet:vider-patients {--force : ne pas demander de confirmation}';

    protected $description = 'Supprime tous les patients, dossiers, rendez-vous, séances, radios, ordonnances, labo et paiements';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Supprimer TOUS les patients et leurs dossiers ? (les comptes restent)')) {
            return self::FAILURE;
        }

        DB::transaction(function () {
            // ordre : des tables « enfants » vers les patients
            foreach (['sms', 'avis', 'plans_a_valider', 'rdv_a_fixer', 'demandes_radio', 'ordonnances', 'documents', 'radios', 'paiements', 'commandes_labo', 'etat_dents', 'actes', 'seances', 'rendez_vous', 'clotures_caisse'] as $table) {
                DB::table($table)->delete();
            }
            DB::table('dossiers')->update(['fusionne_dans_id' => null]);
            DB::table('dossiers')->delete();
            DB::table('patients')->delete();
            DB::table('journal')->whereIn('sujet_type', ['Patient', 'Dossier', 'RendezVous', 'Seance', 'Acte', 'CommandeLabo', 'Paiement', 'Radio', 'ClotureCaisse', 'DemandeRadio', 'Ordonnance', 'RdvAFixer'])->delete();
            DB::table('users')->where('role', 'docteur')->update(['etat' => 'pret', 'etat_depuis' => null]);
        });
        Storage::disk('local')->deleteDirectory('patients');
        \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\AccueilController::CLE_TABLETTE);

        $this->info('Tous les patients ont été supprimés. Les comptes, les salles et le catalogue sont conservés.');

        return self::SUCCESS;
    }
}
